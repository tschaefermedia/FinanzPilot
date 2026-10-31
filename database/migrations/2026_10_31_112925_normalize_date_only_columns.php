<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Date-only columns were stored as "Y-m-d 00:00:00" by Eloquent but as "Y-m-d"
 * by the MCP server. Range queries compare against "Y-m-d" strings, which
 * silently dropped the last day of a range. Normalizes everything to "Y-m-d".
 */
return new class extends Migration
{
    private const DATE_COLUMNS = [
        'transactions' => 'date',
        'scheduled_payments' => 'date',
        'loan_payments' => 'date',
        'recurring_templates' => 'next_due_date',
        'loans' => 'start_date',
    ];

    public function up(): void
    {
        foreach (self::DATE_COLUMNS as $table => $column) {
            DB::table($table)
                ->whereRaw("length({$column}) > 10")
                ->update([$column => DB::raw("substr({$column}, 1, 10)")]);
        }
    }

    public function down(): void
    {
        // Irreversible on purpose: the time part was always 00:00:00.
    }
};
