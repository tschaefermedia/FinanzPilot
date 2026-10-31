<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DateOnlyStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_dates_are_stored_without_time(): void
    {
        $transaction = Transaction::create(['date' => '2026-10-31', 'amount' => -1, 'description' => 'x']);

        $this->assertSame('2026-10-31', DB::table('transactions')->value('date'));
        $this->assertSame('2026-10-31', $transaction->fresh()->date->toDateString());
    }

    public function test_migration_strips_the_time_from_existing_dates(): void
    {
        DB::table('transactions')->insert(['date' => '2026-10-31 00:00:00', 'amount' => -1, 'description' => 'x', 'source' => 'manual']);
        DB::table('transactions')->insert(['date' => '2026-09-30', 'amount' => -1, 'description' => 'y', 'source' => 'manual']);

        (require database_path('migrations/2026_10_31_112925_normalize_date_only_columns.php'))->up();

        $this->assertSame(['2026-09-30', '2026-10-31'], DB::table('transactions')->orderBy('date')->pluck('date')->all());
    }
}
