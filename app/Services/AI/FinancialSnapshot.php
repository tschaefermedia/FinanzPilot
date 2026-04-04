<?php

namespace App\Services\AI;

use App\Models\Loan;
use App\Models\Transaction;
use App\Services\AmortizationService;
use Illuminate\Support\Facades\DB;

class FinancialSnapshot
{
    public function __construct(
        public readonly array $monthlyRatios,      // last 6 months: income=100, expenses as %
        public readonly array $categoryShares,     // expense categories as % of total expenses
        public readonly float $savingsRate,         // current month savings rate %
        public readonly float $savingsRateTrend,    // change vs previous month
        public readonly int $transactionCount,
        public readonly array $topGrowingCategories, // categories with increasing spend
        public readonly array $topShrinkingCategories,
        public readonly array $loanSummary,          // aggregated loan stats
        public readonly string $hash,               // for caching
    ) {}

    /**
     * Build an anonymized snapshot from the database.
     * No absolute amounts, no counterparty names, no personal identifiers.
     */
    public static function capture(): self
    {
        $currentMonth = now()->format('Y-m');
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth()->toDateString();

        // Monthly income and expenses for last 6 months
        $monthly = Transaction::selectRaw("
                strftime('%Y-%m', date) as month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
            ")
            ->where('date', '>=', $sixMonthsAgo)
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderBy('month')
            ->get();

        // Normalize to ratios (income = 100)
        $monthlyRatios = $monthly->map(function ($m) {
            $income = (float) $m->income;
            $expenses = (float) $m->expenses;

            return [
                'month' => $m->month,
                'income' => 100,
                'expenses' => $income > 0 ? round(($expenses / $income) * 100, 1) : 0,
                'savings' => $income > 0 ? round((($income - $expenses) / $income) * 100, 1) : 0,
            ];
        })->values()->toArray();

        // Category shares as % of total expenses (current month)
        $categoryExpenses = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->whereRaw("strftime('%Y-%m', transactions.date) = ?", [$currentMonth])
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $totalExpenses = $categoryExpenses->sum('total');
        $categoryShares = $categoryExpenses->map(fn ($c) => [
            'category' => $c->name,
            'share' => $totalExpenses > 0 ? round(($c->total / $totalExpenses) * 100, 1) : 0,
        ])->toArray();

        // Savings rate
        $currentData = $monthly->firstWhere('month', $currentMonth);
        $currentIncome = (float) ($currentData?->income ?? 0);
        $currentExpenses = (float) ($currentData?->expenses ?? 0);
        $savingsRate = $currentIncome > 0 ? round((($currentIncome - $currentExpenses) / $currentIncome) * 100, 1) : 0;

        // Previous month for trend
        $prevMonth = now()->subMonth()->format('Y-m');
        $prevData = $monthly->firstWhere('month', $prevMonth);
        $prevIncome = (float) ($prevData?->income ?? 0);
        $prevExpenses = (float) ($prevData?->expenses ?? 0);
        $prevSavingsRate = $prevIncome > 0 ? round((($prevIncome - $prevExpenses) / $prevIncome) * 100, 1) : 0;
        $savingsRateTrend = round($savingsRate - $prevSavingsRate, 1);

        // Growing/shrinking categories (compare current vs previous month)
        $prevCategoryExpenses = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->whereRaw("strftime('%Y-%m', transactions.date) = ?", [$prevMonth])
            ->groupBy('categories.name')
            ->get()
            ->keyBy('name');

        $growing = [];
        $shrinking = [];
        foreach ($categoryExpenses as $cat) {
            $prevTotal = (float) ($prevCategoryExpenses[$cat->name]?->total ?? 0);
            $currentTotal = (float) $cat->total;
            if ($prevTotal > 0) {
                $change = round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1);
                if ($change > 10) {
                    $growing[] = ['category' => $cat->name, 'change' => $change];
                }
                if ($change < -10) {
                    $shrinking[] = ['category' => $cat->name, 'change' => $change];
                }
            }
        }

        usort($growing, fn ($a, $b) => $b['change'] <=> $a['change']);
        usort($shrinking, fn ($a, $b) => $a['change'] <=> $b['change']);

        // Loan summary — per-loan details + total monthly burden
        $loanSummary = [];
        $loans = Loan::with('payments')->get();
        if ($loans->isNotEmpty()) {
            $amortization = new AmortizationService;
            $loanDetails = [];
            $totalMonthlyBurden = 0;

            foreach ($loans as $loan) {
                $summary = $amortization->calculateSummary($loan);
                $monthlyPayment = $summary['monthlyPayment'] ?? 0;
                $totalMonthlyBurden += $monthlyPayment;

                $loanDetails[] = [
                    'name' => $loan->name,
                    'type' => $loan->type === 'bank' ? 'Bankdarlehen' : 'Informell',
                    'direction' => $loan->direction === 'owed_by_me' ? 'Schulden' : 'Forderung',
                    'progressPercent' => $summary['progressPercent'],
                    'monthlyPercent' => $currentIncome > 0 ? round(($monthlyPayment / $currentIncome) * 100, 1) : 0,
                ];
            }

            $loanSummary = [
                'count' => count($loanDetails),
                'loans' => $loanDetails,
                'totalMonthlyBurden' => $totalMonthlyBurden,
                'monthlyBurdenPercent' => $currentIncome > 0 ? round(($totalMonthlyBurden / $currentIncome) * 100, 1) : 0,
            ];
        }

        $hash = hash('sha256', json_encode([$monthlyRatios, $categoryShares, $savingsRate, $loanSummary]));

        return new self(
            monthlyRatios: $monthlyRatios,
            categoryShares: $categoryShares,
            savingsRate: $savingsRate,
            savingsRateTrend: $savingsRateTrend,
            transactionCount: Transaction::whereRaw("strftime('%Y-%m', date) = ?", [$currentMonth])->count(),
            topGrowingCategories: array_slice($growing, 0, 3),
            topShrinkingCategories: array_slice($shrinking, 0, 3),
            loanSummary: $loanSummary,
            hash: $hash,
        );
    }

    public function toPromptContext(): string
    {
        $lines = ["Finanzdaten (anonymisiert, alle Werte in Prozent vom Einkommen):\n"];

        $lines[] = 'Monatliche Übersicht (letzte 6 Monate):';
        foreach ($this->monthlyRatios as $m) {
            $lines[] = "  {$m['month']}: Ausgaben={$m['expenses']}% vom Einkommen, Sparquote={$m['savings']}%";
        }

        $lines[] = "\nAusgaben nach Kategorie (aktueller Monat):";
        foreach ($this->categoryShares as $c) {
            $lines[] = "  {$c['category']}: {$c['share']}%";
        }

        $lines[] = "\nAktuelle Sparquote: {$this->savingsRate}%";
        $lines[] = 'Trend zum Vormonat: '.($this->savingsRateTrend >= 0 ? '+' : '')."{$this->savingsRateTrend}%";

        if (! empty($this->topGrowingCategories)) {
            $lines[] = "\nStark gestiegene Kategorien:";
            foreach ($this->topGrowingCategories as $c) {
                $lines[] = "  {$c['category']}: +{$c['change']}%";
            }
        }

        if (! empty($this->topShrinkingCategories)) {
            $lines[] = "\nStark gesunkene Kategorien:";
            foreach ($this->topShrinkingCategories as $c) {
                $lines[] = "  {$c['category']}: {$c['change']}%";
            }
        }

        if (! empty($this->loanSummary)) {
            $lines[] = "\nKredite ({$this->loanSummary['count']}):";
            foreach ($this->loanSummary['loans'] as $loan) {
                $lines[] = "  {$loan['name']}: {$loan['type']}, {$loan['direction']}, Fortschritt {$loan['progressPercent']}%, Rate {$loan['monthlyPercent']}% vom Einkommen";
            }
            $lines[] = "  Gesamte monatliche Kreditbelastung: {$this->loanSummary['monthlyBurdenPercent']}% vom Einkommen";
        }

        return implode("\n", $lines);
    }
}
