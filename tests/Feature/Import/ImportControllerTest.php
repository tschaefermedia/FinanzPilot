<?php

namespace Tests\Feature\Import;

use App\Models\Transaction;
use App\Services\Import\ParsedTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function genericCsvUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('konto.csv', implode("\n", [
            'Datum;Betrag;Text',
            '05.03.2026;-3,50;Bäckerei',
            '05.03.2026;-3,50;Bäckerei',
            '06.03.2026;-12,00;Kino',
        ]));
    }

    public function test_generic_import_parses_uploaded_file_and_keeps_identical_rows_apart(): void
    {
        Storage::fake('local');

        $upload = $this->post('/imports/upload', ['file' => $this->genericCsvUpload()]);
        $upload->assertInertia(fn ($page) => $page->component('Import/ColumnMapping', false));
        $filePath = $upload->viewData('page')['props']['filePath'];

        $preview = $this->post('/imports/parse-generic', [
            'file_path' => $filePath,
            'delimiter' => ';',
            'has_header' => true,
            'columns' => ['date' => 0, 'amount' => 1, 'description' => 2],
            'date_format' => 'd.m.Y',
        ]);

        $preview->assertInertia(fn ($page) => $page
            ->component('Import/Preview', false)
            ->where('totalCount', 3)
            ->where('duplicateCount', 0)
        );
        $hashes = array_column($preview->viewData('page')['props']['transactions'], 'hash');
        $this->assertCount(3, array_unique($hashes));
    }

    public function test_parse_generic_rejects_paths_outside_the_imports_directory(): void
    {
        Storage::fake('local');
        Storage::put('secret.csv', 'x');
        Storage::put('imports/konto.csv', 'x');

        $this->post('/imports/parse-generic', [
            'file_path' => 'imports/../secret.csv',
            'delimiter' => ';',
            'has_header' => true,
            'columns' => ['date' => 0, 'amount' => 1, 'description' => 2],
        ])->assertForbidden();
    }

    public function test_commit_skips_rows_whose_hash_already_exists_including_deleted_ones(): void
    {
        Storage::fake('local');
        Storage::put('imports/konto.csv', 'x');
        $deleted = Transaction::create(['date' => '2026-03-05', 'amount' => -3.5, 'description' => 'Alt', 'hash' => 'abc']);
        $deleted->delete();

        $this->post('/imports/commit', [
            'filename' => 'konto.csv',
            'source_type' => 'generic',
            'storage_path' => 'imports/konto.csv',
            'transactions' => [
                ['date' => '2026-03-05', 'amount' => -3.5, 'description' => 'Bäckerei', 'hash' => 'abc'],
                ['date' => '2026-03-06', 'amount' => -12, 'description' => 'Kino', 'hash' => 'def'],
            ],
        ])->assertSessionHas('success', '1 Buchungen importiert.');

        $this->assertSame(1, Transaction::count());
        Storage::assertMissing('imports/konto.csv');
    }

    public function test_hash_disambiguation_is_stable_across_reimports(): void
    {
        $row = fn () => new ParsedTransaction('2026-03-05', -3.5, 'Bäckerei', null, null, ParsedTransaction::computeHash('2026-03-05', -3.5, 'Bäckerei'));

        $first = ParsedTransaction::disambiguateHashes([$row(), $row()]);
        $second = ParsedTransaction::disambiguateHashes([$row(), $row()]);

        $this->assertNotSame($first[0]->hash, $first[1]->hash);
        $this->assertSame($row()->hash, $first[0]->hash);
        $this->assertSame(array_column($first, 'hash'), array_column($second, 'hash'));
    }

    public function test_prune_command_deletes_only_stale_uploads(): void
    {
        Storage::fake('local');
        Storage::put('imports/alt.csv', 'x');
        touch(Storage::path('imports/alt.csv'), now()->subDays(2)->getTimestamp());
        Storage::put('imports/neu.csv', 'x');

        $this->artisan('imports:prune-uploads')->assertSuccessful();

        Storage::assertMissing('imports/alt.csv');
        Storage::assertExists('imports/neu.csv');
    }
}
