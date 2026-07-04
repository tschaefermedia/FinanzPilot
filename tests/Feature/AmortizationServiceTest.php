<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Services\AmortizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmortizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bankLoan(array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'name' => 'Test',
            'type' => 'bank',
            'direction' => 'owed',
            'principal' => 10000,
            'interest_rate' => 6,
            'term_months' => 12,
            'start_date' => '2026-01-01',
        ], $overrides));
    }

    public function test_zero_interest_schedule_splits_principal_evenly(): void
    {
        $loan = $this->bankLoan(['principal' => 12000, 'interest_rate' => 0, 'term_months' => 12]);

        $schedule = (new AmortizationService)->calculateSchedule($loan);

        $this->assertCount(12, $schedule);
        $this->assertSame(1000.0, $schedule[0]['payment']);
        $this->assertSame(1000.0, $schedule[0]['principal']);
        $this->assertSame(0.0, $schedule[0]['interest']);
        $this->assertSame(0.0, $schedule[11]['balance']);
    }

    public function test_annuity_schedule_amortizes_to_zero_with_interest(): void
    {
        $loan = $this->bankLoan(); // 10000 @ 6% over 12 months

        $schedule = (new AmortizationService)->calculateSchedule($loan);

        $this->assertCount(12, $schedule);
        // First month interest = 10000 * (6%/12) = 50.00
        $this->assertSame(50.0, $schedule[0]['interest']);
        // Balance is fully repaid on the last payment
        $this->assertSame(0.0, $schedule[11]['balance']);
        // Principal portions sum back to the original principal
        $principalPaid = array_sum(array_column($schedule, 'principal'));
        $this->assertEqualsWithDelta(10000, $principalPaid, 0.02);
        // There is a positive total interest cost
        $this->assertGreaterThan(0, array_sum(array_column($schedule, 'interest')));
    }

    public function test_summary_reports_remaining_balance_and_progress(): void
    {
        $loan = $this->bankLoan(['principal' => 12000, 'interest_rate' => 0, 'term_months' => 12]);

        $summary = (new AmortizationService)->calculateSummary($loan);

        // No payments recorded yet
        $this->assertSame(12000.0, $summary['remainingBalance']);
        $this->assertSame(0.0, $summary['progressPercent']);
        $this->assertSame(0.0, $summary['totalInterest']);
        $this->assertArrayHasKey('schedule', $summary);
    }

    public function test_non_bank_loan_has_no_schedule(): void
    {
        $loan = Loan::create([
            'name' => 'Freund',
            'type' => 'informal',
            'direction' => 'owed_to_me',
            'principal' => 500,
            'start_date' => '2026-01-01',
        ]);

        $this->assertSame([], (new AmortizationService)->calculateSchedule($loan));
    }
}
