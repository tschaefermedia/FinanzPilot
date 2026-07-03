<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class AmortizationService
{
    /**
     * Calculate a full amortization schedule for a bank loan.
     * Returns an array of monthly payment entries.
     */
    public function calculateSchedule(Loan $loan): array
    {
        if ($loan->type !== 'bank' || ! $loan->term_months || $loan->term_months <= 0) {
            return [];
        }

        $principal = (float) $loan->principal;
        $annualRate = (float) $loan->interest_rate / 100;
        $monthlyRate = $annualRate / 12;
        $months = $loan->term_months;

        // Calculate monthly payment (annuity formula)
        if ($monthlyRate > 0) {
            $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        } else {
            $monthlyPayment = $principal / $months;
        }

        $schedule = [];
        $balance = $principal;
        $date = $loan->start_date->copy();

        for ($i = 1; $i <= $months; $i++) {
            $interestPortion = $balance * $monthlyRate;
            $principalPortion = $monthlyPayment - $interestPortion;

            // Last payment adjustment to avoid rounding errors
            if ($i === $months) {
                $principalPortion = $balance;
                $monthlyPayment = $principalPortion + $interestPortion;
            }

            $balance -= $principalPortion;

            $schedule[] = [
                'month' => $i,
                'date' => $date->format('Y-m-d'),
                'payment' => round($monthlyPayment, 2),
                'principal' => round($principalPortion, 2),
                'interest' => round($interestPortion, 2),
                'balance' => round(max(0, $balance), 2),
            ];

            $date = $date->addMonth();
        }

        return $schedule;
    }

    /**
     * Calculate summary statistics for a loan.
     */
    public function calculateSummary(Loan $loan): array
    {
        $schedule = $this->calculateSchedule($loan);

        if (empty($schedule)) {
            // Informal loan or bank loan without full schedule — simple summary
            $totalPaid = $loan->payments->sum('amount');
            $principal = (float) $loan->principal;
            $initialBalance = (float) ($loan->initial_balance ?? $principal);
            $prePaid = $principal - $initialBalance;
            $remainingBalance = round($initialBalance - $totalPaid, 2);
            $monthlyRate = (float) ($loan->monthly_rate ?? 0);

            $summary = [
                'totalPayments' => round($totalPaid, 2),
                'remainingBalance' => $remainingBalance,
                'totalInterest' => 0,
                'monthlyPayment' => $monthlyRate,
                'progressPercent' => $principal > 0 ? round((($prePaid + $totalPaid) / $principal) * 100, 1) : 0,
            ];

            if ($monthlyRate > 0 && $remainingBalance > 0) {
                $remainingMonths = (int) ceil($remainingBalance / $monthlyRate);
                $summary['remainingMonths'] = $remainingMonths;
                $summary['expectedPayoffDate'] = now()->addMonths($remainingMonths)->format('Y-m-d');
            }

            return $summary;
        }

        $totalPayments = array_sum(array_column($schedule, 'payment'));
        $totalInterest = array_sum(array_column($schedule, 'interest'));
        $monthlyPayment = $loan->monthly_rate ? (float) $loan->monthly_rate : ($schedule[0]['payment'] ?? 0);

        // Calculate actual remaining balance based on payments made
        $totalPaid = $loan->payments->sum('amount');
        $principal = (float) $loan->principal;
        $initialBalance = (float) ($loan->initial_balance ?? $principal);
        $prePaid = $principal - $initialBalance;
        $paidPrincipal = 0;

        foreach ($schedule as $entry) {
            if ($totalPaid >= $entry['payment']) {
                $paidPrincipal += $entry['principal'];
                $totalPaid -= $entry['payment'];
            } else {
                break;
            }
        }

        $remaining = $initialBalance - $paidPrincipal;

        return [
            'totalPayments' => round($totalPayments, 2),
            'remainingBalance' => round(max(0, $remaining), 2),
            'totalInterest' => round($totalInterest, 2),
            'monthlyPayment' => round($monthlyPayment, 2),
            'progressPercent' => $principal > 0 ? round((($prePaid + $paidPrincipal) / $principal) * 100, 1) : 0,
            'schedule' => $schedule,
        ];
    }

    /**
     * Try to auto-match imported transactions as loan payments.
     * Matches by amount and approximate date.
     */
    public function autoMatchPayments(Loan $loan): int
    {
        if (! $loan->payment_day) {
            return 0;
        }

        $schedule = $this->calculateSchedule($loan);
        if (empty($schedule)) {
            return 0;
        }

        // Match against the configured monthly rate when set (the actual debit),
        // falling back to the computed annuity payment.
        $monthlyAmount = $loan->monthly_rate > 0
            ? abs((float) $loan->monthly_rate)
            : abs($schedule[0]['payment']);
        $matched = 0;

        // Find unmatched transactions near the payment amount.
        // CAST(... AS REAL) is required: SQLite binds PHP floats as text, and a
        // numeric column compared to a text bound value never matches a BETWEEN.
        $query = Transaction::where('amount', '<', 0)
            ->whereRaw('ABS(amount) BETWEEN CAST(? AS REAL) AND CAST(? AS REAL)', [$monthlyAmount * 0.95, $monthlyAmount * 1.05])
            ->whereDoesntHave('loanPayment')
            ->where('date', '>=', $loan->start_date);

        if ($loan->account_id) {
            $query->where('account_id', $loan->account_id);
        }

        if ($loan->match_description) {
            $query->where(function ($q) use ($loan) {
                $q->where('description', 'like', '%'.$loan->match_description.'%')
                    ->orWhere('counterparty', 'like', '%'.$loan->match_description.'%')
                    ->orWhere('reference', 'like', '%'.$loan->match_description.'%');
            });
        }

        $transactions = $query->get();

        // Pre-load existing payment months to avoid N+1
        $existingMonths = $loan->payments->map(fn ($p) => $p->date->format('Y-m'))->toArray();

        foreach ($transactions as $transaction) {
            // Check if payment day is close (wrapping across month boundaries)
            if ($this->isNearPaymentDay($transaction->date, (int) $loan->payment_day)) {
                // Check if we don't already have a payment for this month
                $monthKey = $transaction->date->format('Y-m');

                if (! in_array($monthKey, $existingMonths)) {
                    LoanPayment::create([
                        'loan_id' => $loan->id,
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->date,
                        'amount' => abs((float) $transaction->amount),
                        'type' => 'scheduled',
                    ]);
                    $existingMonths[] = $monthKey;
                    $matched++;
                }
            }
        }

        return $matched;
    }

    /**
     * Whether a transaction date falls within tolerance of the loan's payment day.
     * Considers the payment day in the previous, current, and next month so a
     * debit that shifts across a month boundary (e.g. weekend) still matches.
     */
    private function isNearPaymentDay(Carbon $date, int $paymentDay, int $tolerance = 3): bool
    {
        foreach ([-1, 0, 1] as $offset) {
            $month = $date->copy()->startOfMonth()->addMonthsNoOverflow($offset);
            $expected = $month->copy()->day(min($paymentDay, $month->daysInMonth));

            if (abs($date->diffInDays($expected)) <= $tolerance) {
                return true;
            }
        }

        return false;
    }
}
