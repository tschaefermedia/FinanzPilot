<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    /**
     * VACUUM cannot run inside the transaction RefreshDatabase wraps each test in,
     * and the in-memory database starts empty for every test anyway.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_it_writes_a_readable_copy_of_the_database(): void
    {
        $disk = Storage::fake('backups');
        Category::create(['name' => 'Miete', 'type' => 'expense']);
        $this->travelTo('2026-10-30 02:00:00');

        $this->artisan('backup:database')->assertSuccessful();

        $backupPath = $disk->path('finanzpilot-2026-10-30-020000.sqlite');
        $this->assertFileExists($backupPath);
        $backup = new PDO('sqlite:'.$backupPath);
        $this->assertSame('Miete', $backup->query('SELECT name FROM categories')->fetchColumn());
    }

    public function test_it_keeps_only_the_newest_backups(): void
    {
        $disk = Storage::fake('backups');
        $disk->put('finanzpilot-2026-10-27-020000.sqlite', '');
        $disk->put('finanzpilot-2026-10-28-020000.sqlite', '');
        $disk->put('finanzpilot-2026-10-29-020000.sqlite', '');
        $disk->put('unrelated.sqlite', '');
        $this->travelTo('2026-10-30 02:00:00');

        $this->artisan('backup:database', ['--keep' => 2])->assertSuccessful();

        $this->assertEqualsCanonicalizing([
            'finanzpilot-2026-10-29-020000.sqlite',
            'finanzpilot-2026-10-30-020000.sqlite',
            'unrelated.sqlite',
        ], $disk->files());
    }
}
