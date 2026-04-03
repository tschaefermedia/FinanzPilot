<?php

namespace App\Services;

use App\Models\Loan;

class AmortizationService
{
    /**
     * Calculate a full amortization schedule for a bank loan.
     * Returns an array of monthly payment entries.
     */
    public function calculateSchedule(Loan $loan): array
    {
        if ($loan->type !== 'bank' || !$loan->term_months || $loan->term_months <= 0) {
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
            return [
                'totalPayments' => round($totalPaid, 2),
                'remainingBalance' => round((float) $loan->principal - $totalPaid, 2),
                'totalInterest' => 0,
                'monthlyPayment' => 0,
                'progressPercent' => $loan->principal > 0 ? round(($totalPaid / (float) $loan->principal) * 100, 1) : 0,
            ];
        }

        $totalPayments = array_sum(array_column($schedule, 'payment'));
        $totalInterest = array_sum(array_column($schedule, 'interest'));
        $monthlyPayment = $schedule[0]['payment'] ?? 0;

        // Calculate actual remaining balance based on payments made
        $totalPaid = $loan->payments->sum('amount');
        $paidPrincipal = 0;
        $remaining = (float) $loan->principal;

        foreach ($schedule as $entry) {
            if ($totalPaid >= $entry['payment']) {
                $paidPrincipal += $entry['principal'];
                $totalPaid -= $entry['payment'];
            } else {
                break;
            }
        }

        $remaining -= $paidPrincipal;

        return [
            'totalPayments' => round($totalPayments, 2),
            'remainingBalance' => round(max(0, $remaining), 2),
            'totalInterest' => round($totalInterest, 2),
            'monthlyPayment' => round($monthlyPayment, 2),
            'progressPercent' => $loan->principal > 0 ? round(($paidPrincipal / (float) $loan->principal) * 100, 1) : 0,
            'schedule' => $schedule,
        ];
    }

    /**
     * Try to auto-match imported transactions as loan payments.
     * Matches by amount and approximate date.
     */
    public function autoMatchPayments(Loan $loan): int
    {
        if (!$loan->payment_day) {
            return 0;
        }

        $schedule = $this->calculateSchedule($loan);
        if (empty($schedule)) {
            return 0;
        }

        $monthlyAmount = abs($schedule[0]['payment']);
        $matched = 0;

        // Find unmatched transactions near the payment amount
        $transactions = \App\Models\Transaction::where('amount', '<', 0)
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('ABS(amount)'), [$monthlyAmount * 0.95, $monthlyAmount * 1.05])
            ->whereDoesntHave('loanPayment')
            ->where('date', '>=', $loan->start_date)
            ->get();

        foreach ($transactions as $transaction) {
            // Check if payment day is close
            $txDay = $transaction->date->day;
            if (abs($txDay - $loan->payment_day) <= 3) {
                // Check if we don't already have a payment for this month
                $existingPayment = $loan->payments()
                    ->whereYear('date', $transaction->date->year)
                    ->whereMonth('date', $transaction->date->month)
                    ->exists();

                if (!$existingPayment) {
                    \App\Models\LoanPayment::create([
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
