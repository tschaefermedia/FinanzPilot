<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

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
            // Informal loan — simple summary
            $totalPaid = $loan->payments->sum('amount');
            $base = (float) ($loan->initial_balance ?? $loan->principal);

            return [
                'totalPayments' => round($totalPaid, 2),
                'remainingBalance' => round($base - $totalPaid, 2),
                'totalInterest' => 0,
                'monthlyPayment' => (float) ($loan->monthly_rate ?? 0),
                'progressPercent' => $base > 0 ? round(($totalPaid / $base) * 100, 1) : 0,
            ];
        }

        $totalPayments = array_sum(array_column($schedule, 'payment'));
        $totalInterest = array_sum(array_column($schedule, 'interest'));
        $monthlyPayment = $schedule[0]['payment'] ?? 0;

        // Calculate actual remaining balance based on payments made
        $totalPaid = $loan->payments->sum('amount');
        $paidPrincipal = 0;
        $base = (float) ($loan->initial_balance ?? $loan->principal);

        foreach ($schedule as $entry) {
            if ($totalPaid >= $entry['payment']) {
                $paidPrincipal += $entry['principal'];
                $totalPaid -= $entry['payment'];
            } else {
                break;
            }
        }

        $remaining = $base - $paidPrincipal;

        return [
            'totalPayments' => round($totalPayments, 2),
            'remainingBalance' => round(max(0, $remaining), 2),
            'totalInterest' => round($totalInterest, 2),
            'monthlyPayment' => round($monthlyPayment, 2),
            'progressPercent' => $base > 0 ? round(($paidPrincipal / $base) * 100, 1) : 0,
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

        $monthlyAmount = abs($schedule[0]['payment']);
        $matched = 0;

        // Find unmatched transactions near the payment amount
        $query = Transaction::where('amount', '<', 0)
            ->whereBetween(DB::raw('ABS(amount)'), [$monthlyAmount * 0.95, $monthlyAmount * 1.05])
            ->whereDoesntHave('loanPayment')
            ->where('date', '>=', $loan->start_date);

        if ($loan->account_id) {
            $query->where('account_id', $loan->account_id);
        }

        if ($loan->match_description) {
            $query->where('description', 'like', '%'.$loan->match_description.'%');
        }

        $transactions = $query->get();

        foreach ($transactions as $transaction) {
            // Check if payment day is close
            $txDay = $transaction->date->day;
            if (abs($txDay - $loan->payment_day) <= 3) {
                // Check if we don't already have a payment for this month
                $existingPayment = $loan->payments()
                    ->whereYear('date', $transaction->date->year)
                    ->whereMonth('date', $transaction->date->month)
                    ->exists();

                if (! $existingPayment) {
                    LoanPayment::create([
                        'loan_id' => $loan->id,
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->date,
                        'amount' => abs((float) $transaction->amount),
                        'type' => 'scheduled',
                    ]);
                    $matched++;
                }
            }
        }

        return $matched;
    }
}
