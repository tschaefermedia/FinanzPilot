<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite doesn't enforce enum constraints from the column definition,
        // so we just need to ensure the value is accepted.
        // The enum was defined in the original migration as a string check,
        // but SQLite stores it as TEXT regardless.
        // This migration documents the addition of 'generic' as a valid source value.
    }

    public function down(): void
    {
        //
    }
};
