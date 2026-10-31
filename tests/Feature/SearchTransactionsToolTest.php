<?php

namespace Tests\Feature;

use App\Ai\Tools\SearchTransactions;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SearchTransactionsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_sent_to_the_ai_provider_omit_transaction_descriptions(): void
    {
        Transaction::create(['date' => now()->toDateString(), 'amount' => 2000, 'description' => 'Gehalt']);
        Transaction::create(['date' => now()->toDateString(), 'amount' => -100, 'description' => 'Max Mustermann DE89370400440532013000']);

        $result = (new SearchTransactions)->handle(new Request(['keyword' => 'Mustermann']));

        $this->assertStringContainsString('5% vom Einkommen', $result);
        $this->assertStringNotContainsString('Mustermann', $result);
        $this->assertStringNotContainsString('DE89', $result);
    }
}
