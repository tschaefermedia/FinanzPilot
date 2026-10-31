<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The settings page used to prefill "claude-sonnet-4-5-20250514", which was
 * never a valid model ID. Installs that saved it get the current default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'ai_model')
            ->where('value', 'claude-sonnet-4-5-20250514')
            ->update(['value' => 'claude-opus-5-5']);
    }

    public function down(): void
    {
        // Irreversible: restoring an invalid model ID has no value.
    }
};
