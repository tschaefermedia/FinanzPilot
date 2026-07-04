<?php

namespace Tests\Feature;

use App\Models\RecurringTemplate;
use App\Models\Transaction;
use App\Services\RecurringDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function tx(string $date, float $amount, string $counterparty, string $description = 'x'): void
    {
        Transaction::create([
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'counterparty' => $counterparty,
            'source' => 'import',
        ]);
    }

    public function test_detects_monthly_recurring_payment(): void
    {
        foreach (['2026-01-05', '2026-02-05', '2026-03-05', '2026-04-05'] as $date) {
            $this->tx($date, -9.99, 'Netflix', 'Netflix Abo');
        }

        $suggestions = (new RecurringDetector)->detect();

        $this->assertCount(1, $suggestions);
        $this->assertSame('monthly', $suggestions[0]['frequency']);
        $this->assertSame(-9.99, $suggestions[0]['amount']);
        $this->assertSame('2026-05-05', $suggestions[0]['next_due_date']);
        $this->assertSame(4, $suggestions[0]['occurrences']);
    }

    public function test_ignores_one_off_transactions(): void
    {
        $this->tx('2026-01-05', -42.00, 'Amazon');
        $this->tx('2026-02-11', -13.50, 'Media Markt');

        $this->assertSame([], (new RecurringDetector)->detect());
    }

    public function test_skips_payments_already_covered_by_a_template(): void
    {
        foreach (['2026-01-05', '2026-02-05', '2026-03-05'] as $date) {
            $this->tx($date, -9.99, 'Netflix', 'Netflix Abo');
        }

        RecurringTemplate::create([
            'description' => 'Netflix Abo',
            'amount' => -9.99,
            'frequency' => 'monthly',
            'next_due_date' => '2026-04-05',
            'is_active' => true,
        ]);

        $this->assertSame([], (new RecurringDetector)->detect());
    }
}
