<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@finanzpilot.local'],
            [
                'name' => 'Admin',
                'password' => bcrypt('finanzpilot'),
            ]
        );
    }
}
