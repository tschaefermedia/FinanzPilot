<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Earlier versions seeded admin@finanzpilot.local with the public password
 * "finanzpilot". Now that login is enforced, that account would grant anyone
 * access, so it is removed while it still has the default password. Without a
 * user, the app shows the first-run setup screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultAdmin = DB::table('users')->where('email', 'admin@finanzpilot.local')->first();

        if ($defaultAdmin && Hash::check('finanzpilot', $defaultAdmin->password)) {
            DB::table('users')->where('id', $defaultAdmin->id)->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: recreating a user with a public password is never wanted.
    }
};
