<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_history_is_the_real_month_end_balance_of_active_accounts(): void
    {
        $this->travelTo('2026-10-15');
        $checking = Account::create(['name' => 'Giro', 'type' => 'checking', 'starting_balance' => 1000]);
        $inactive = Account::create(['name' => 'Alt', 'type' => 'savings', 'starting_balance' => 500, 'is_active' => false]);

        // Older than the 12-month window: must still count towards the balance.
        Transaction::create(['date' => '2025-01-10', 'amount' => 200, 'description' => 'Alt', 'account_id' => $checking->id]);
        Transaction::create(['date' => '2026-09-10', 'amount' => -50, 'description' => 'Einkauf', 'account_id' => $checking->id]);
        Transaction::create(['date' => '2026-10-01', 'amount' => 300, 'description' => 'Gehalt', 'account_id' => $checking->id]);
        Transaction::create(['date' => '2026-10-02', 'amount' => -999, 'description' => 'Ignoriert', 'account_id' => $inactive->id]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->component('Dashboard', false)
            ->has('balanceData', 13)
            ->where('balanceData.0', ['month' => '2025-10', 'balance' => 1200])
            ->where('balanceData.11', ['month' => '2026-09', 'balance' => 1150])
            ->where('balanceData.12', ['month' => '2026-10', 'balance' => 1450])
            ->where('totalBalance', 1450)
        );
    }
}
