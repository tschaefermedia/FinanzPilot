<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Transaction;
use App\Services\AmortizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPaymentMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function bankLoan(array $overrides = []): Loan
    {
        // Computed annuity is 10000 / 100 = 100/month; monthly_rate is the real debit.
        return Loan::create(array_merge([
            'name' => 'Test',
            'type' => 'bank',
            'direction' => 'owed',
            'principal' => 10000,
            'interest_rate' => 0,
            'term_months' => 100,
            'start_date' => '2026-01-01',
            'payment_day' => 15,
            'monthly_rate' => 300,
        ], $overrides));
    }

    public function test_auto_match_uses_configured_monthly_rate_not_computed_annuity(): void
    {
        $loan = $this->bankLoan();
        $transaction = Transaction::create([
            'date' => '2026-02-15',
            'amount' => -300, // matches monthly_rate (300), far from annuity (100)
            'description' => 'Rate',
        ]);

        $matched = (new AmortizationService)->autoMatchPayments($loan);

        $this->assertSame(1, $matched);
        $this->assertDatabaseHas('loan_payments', [
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_auto_match_wraps_month_boundary_for_payment_day(): void
    {
        $loan = $this->bankLoan(['payment_day' => 30]);
        // Due on the 30th but debited on the 1st of the next month.
        $transaction = Transaction::create([
            'date' => '2026-03-01',
            'amount' => -300,
            'description' => 'Rate',
        ]);

        $matched = (new AmortizationService)->autoMatchPayments($loan);

        $this->assertSame(1, $matched);
        $this->assertDatabaseHas('loan_payments', [
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_unmatched_transactions_can_be_searched(): void
    {
        $loan = $this->bankLoan();
        Transaction::create(['date' => '2026-02-15', 'amount' => -300, 'description' => 'Sparkasse Darlehen']);
        Transaction::create(['date' => '2026-02-16', 'amount' => -42, 'description' => 'Amazon']);

        $this->getJson("/loans/{$loan->id}/unmatched-transactions?search=Sparkasse")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['description' => 'Sparkasse Darlehen']);
    }

    public function test_unmatched_transactions_can_be_searched_by_amount(): void
    {
        $loan = $this->bankLoan();
        Transaction::create(['date' => '2026-02-15', 'amount' => -300, 'description' => 'Darlehen']);
        Transaction::create(['date' => '2026-02-16', 'amount' => -42, 'description' => 'Amazon']);

        $this->getJson("/loans/{$loan->id}/unmatched-transactions?search=300")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['description' => 'Darlehen']);
    }
}
