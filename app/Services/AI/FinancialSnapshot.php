<?php

namespace App\Services\AI;

use App\Models\Category;
use App\Models\Loan;
use App\Models\RecurringTemplate;
use App\Models\Transaction;
use App\Services\AmortizationService;
use Illuminate\Support\Facades\DB;

class FinancialSnapshot
{
    public function __construct(
        public readonly array $monthlyRatios,           // last 12 months: income=100, expenses as %
        public readonly array $categoryShares,          // expense categories as % of total expenses
        public readonly float $savingsRate,              // current month savings rate %
        public readonly float $savingsRateTrend,         // change vs previous month
        public readonly int $transactionCount,
        public readonly array $topGrowingCategories,     // categories with increasing spend
        public readonly array $topShrinkingCategories,
        public readonly array $loanSummary,              // aggregated loan stats
        public readonly array $budgetUtilization,        // category budget vs actual
        public readonly float $recurringCoveragePercent, // % of expenses covered by recurring
        public readonly float $incomeStability,          // coefficient of variation (lower = more stable)
        public readonly array $categoryTrends,           // 12-month category trends
        public readonly array $anomalies,                // detected anomalies
        public readonly string $hash,                    // for caching
    ) {}

    /**
     * Build an anonymized snapshot from the database.
     * No absolute amounts, no counterparty names, no personal identifiers.
     */
    public static function capture(): self
    {
        $currentMonth = now()->format('Y-m');
        $prevMonth = now()->subMonth()->format('Y-m');
        $twelveMonthsAgo = now()->subMonths(12)->startOfMonth()->toDateString();

        // Monthly income and expenses for last 12 months
        $monthly = Transaction::selectRaw("
                strftime('%Y-%m', date) as month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
            ")
            ->where('date', '>=', $twelveMonthsAgo)
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'))
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
            ->where('categories.type', '!=', 'transfer')
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
        $prevData = $monthly->firstWhere('month', $prevMonth);
        $prevIncome = (float) ($prevData?->income ?? 0);
        $prevExpenses = (float) ($prevData?->expenses ?? 0);
        $prevSavingsRate = $prevIncome > 0 ? round((($prevIncome - $prevExpenses) / $prevIncome) * 100, 1) : 0;
        $savingsRateTrend = round($savingsRate - $prevSavingsRate, 1);

        // Growing/shrinking categories (compare current vs previous month)
        $prevCategoryExpenses = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
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
        $loanSummary = self::captureLoanSummary($currentIncome);

        // Budget utilization — categories with budgets
        $budgetUtilization = self::captureBudgetUtilization($currentMonth, $currentIncome);

        // Recurring coverage — % of expenses covered by Daueraufträge
        $recurringCoverage = self::captureRecurringCoverage($currentExpenses);

        // Income stability — coefficient of variation over available months
        $incomeStability = self::captureIncomeStability($monthly);

        // Category trends — 12-month per-category trends (top categories)
        $categoryTrends = self::captureCategoryTrends($twelveMonthsAgo, $monthly);

        // Anomaly detection
        $anomalies = self::captureAnomalies($currentMonth, $prevMonth, $categoryExpenses, $prevCategoryExpenses);

        $hash = hash('sha256', json_encode([
            $monthlyRatios, $categoryShares, $savingsRate,
            $loanSummary, $budgetUtilization, $categoryTrends,
        ]));

        return new self(
            monthlyRatios: $monthlyRatios,
            categoryShares: $categoryShares,
            savingsRate: $savingsRate,
            savingsRateTrend: $savingsRateTrend,
            transactionCount: Transaction::whereRaw("strftime('%Y-%m', date) = ?", [$currentMonth])->count(),
            topGrowingCategories: array_slice($growing, 0, 3),
            topShrinkingCategories: array_slice($shrinking, 0, 3),
            loanSummary: $loanSummary,
            budgetUtilization: $budgetUtilization,
            recurringCoveragePercent: $recurringCoverage,
            incomeStability: $incomeStability,
            categoryTrends: $categoryTrends,
            anomalies: $anomalies,
            hash: $hash,
        );
    }

    private static function captureLoanSummary(float $currentIncome): array
    {
        $loans = Loan::with('payments')->get();
        if ($loans->isEmpty()) {
            return [];
        }

        $amortization = new AmortizationService;
        $loanDetails = [];
        $totalMonthlyBurden = 0;
        $loanIndex = 0;

        foreach ($loans as $loan) {
            $summary = $amortization->calculateSummary($loan);
            $monthlyPayment = $summary['monthlyPayment'] ?? 0;
            $totalMonthlyBurden += $monthlyPayment;

            $loanDetails[] = [
                'name' => 'Kredit '.chr(65 + $loanIndex),
                'type' => $loan->type === 'bank' ? 'Bankdarlehen' : 'Informell',
                'direction' => $loan->direction === 'owed_by_me' ? 'Schulden' : 'Forderung',
                'progressPercent' => $summary['progressPercent'],
                'monthlyPercent' => $currentIncome > 0 ? round(($monthlyPayment / $currentIncome) * 100, 1) : 0,
            ];
            $loanIndex++;
        }

        return [
            'count' => count($loanDetails),
            'loans' => $loanDetails,
            'totalMonthlyBurden' => $totalMonthlyBurden,
            'monthlyBurdenPercent' => $currentIncome > 0 ? round(($totalMonthlyBurden / $currentIncome) * 100, 1) : 0,
        ];
    }

    private static function captureBudgetUtilization(string $currentMonth, float $currentIncome): array
    {
        $categoriesWithBudget = Category::whereNotNull('budget_monthly')
            ->where('budget_monthly', '>', 0)
            ->get();

        if ($categoriesWithBudget->isEmpty()) {
            return [];
        }

        $dayOfMonth = now()->day;
        $daysInMonth = now()->daysInMonth;
        $monthProgress = round(($dayOfMonth / $daysInMonth) * 100, 1);

        $utilization = [];
        foreach ($categoriesWithBudget as $category) {
            $spent = Transaction::where('category_id', $category->id)
                ->where('amount', '<', 0)
                ->whereRaw("strftime('%Y-%m', date) = ?", [$currentMonth])
                ->sum(DB::raw('ABS(amount)'));

            $budget = (float) $category->budget_monthly;
            $spentPercent = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;
            $projected = $dayOfMonth > 0 ? round(($spent / $dayOfMonth) * $daysInMonth, 2) : 0;
            $projectedPercent = $budget > 0 ? round(($projected / $budget) * 100, 1) : 0;

            $utilization[] = [
                'category' => $category->name,
                'budgetPercentOfIncome' => $currentIncome > 0 ? round(($budget / $currentIncome) * 100, 1) : 0,
                'spentPercent' => $spentPercent,
                'projectedPercent' => $projectedPercent,
                'monthProgress' => $monthProgress,
                'status' => $projectedPercent > 110 ? 'over' : ($projectedPercent > 90 ? 'warning' : 'on_track'),
            ];
        }

        return $utilization;
    }

    private static function captureRecurringCoverage(float $currentExpenses): float
    {
        if ($currentExpenses <= 0) {
            return 0;
        }

        $monthlyRecurring = RecurringTemplate::where('is_active', true)
            ->where('amount', '<', 0)
            ->get()
            ->sum(function ($template) {
                $amount = abs((float) $template->amount);

                return match ($template->frequency) {
                    'weekly' => $amount * 4.33,
                    'monthly' => $amount,
                    'quarterly' => $amount / 3,
                    'yearly' => $amount / 12,
                    default => $amount,
                };
            });

        return round(($monthlyRecurring / $currentExpenses) * 100, 1);
    }

    private static function captureIncomeStability($monthly): float
    {
        $incomes = $monthly->pluck('income')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0)->values();

        if ($incomes->count() < 3) {
            return 0;
        }

        $mean = $incomes->avg();
        $variance = $incomes->map(fn ($v) => pow($v - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        // Coefficient of variation: lower = more stable
        return $mean > 0 ? round(($stdDev / $mean) * 100, 1) : 0;
    }

    private static function captureCategoryTrends(string $twelveMonthsAgo, $monthly): array
    {
        // Get per-category monthly totals for last 12 months
        $categoryMonthly = Transaction::select(
            'categories.name',
            DB::raw("strftime('%Y-%m', transactions.date) as month"),
            DB::raw('SUM(ABS(transactions.amount)) as total')
        )
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
            ->where('transactions.date', '>=', $twelveMonthsAgo)
            ->groupBy('categories.name', DB::raw("strftime('%Y-%m', transactions.date)"))
            ->get();

        // Get top categories by total spend
        $topCategories = $categoryMonthly->groupBy('name')
            ->map(fn ($group) => $group->sum('total'))
            ->sortDesc()
            ->take(8)
            ->keys();

        // Build monthly income lookup for normalization
        $monthlyIncome = $monthly->pluck('income', 'month')
            ->map(fn ($v) => (float) $v);

        $trends = [];
        foreach ($topCategories as $categoryName) {
            $catData = $categoryMonthly->where('name', $categoryName);
            $monthlyShares = [];
            foreach ($catData as $row) {
                $income = $monthlyIncome[$row->month] ?? 0;
                $monthlyShares[] = [
                    'month' => $row->month,
                    'percentOfIncome' => $income > 0 ? round(((float) $row->total / $income) * 100, 1) : 0,
                ];
            }
            usort($monthlyShares, fn ($a, $b) => $a['month'] <=> $b['month']);

            $trends[] = [
                'category' => $categoryName,
                'months' => $monthlyShares,
            ];
        }

        return $trends;
    }

    private static function captureAnomalies(
        string $currentMonth,
        string $prevMonth,
        $categoryExpenses,
        $prevCategoryExpenses,
    ): array {
        $anomalies = [];

        // Large single transactions (>2x category monthly average over last 3 months)
        $threeMonthsAgo = now()->subMonths(3)->startOfMonth()->toDateString();
        $categoryAvgs = Transaction::select('category_id', DB::raw('AVG(ABS(amount)) as avg_amount'))
            ->where('amount', '<', 0)
            ->where('date', '>=', $threeMonthsAgo)
            ->whereRaw("strftime('%Y-%m', date) != ?", [$currentMonth])
            ->groupBy('category_id')
            ->pluck('avg_amount', 'category_id');

        $currentTransactions = Transaction::with('category')
            ->where('amount', '<', 0)
            ->whereRaw("strftime('%Y-%m', date) = ?", [$currentMonth])
            ->whereHas('category', fn ($q) => $q->where('type', '!=', 'transfer'))
            ->get();

        foreach ($currentTransactions as $tx) {
            $avg = (float) ($categoryAvgs[$tx->category_id] ?? 0);
            if ($avg > 0 && abs((float) $tx->amount) > $avg * 2) {
                $anomalies[] = [
                    'type' => 'large_transaction',
                    'category' => $tx->category?->name ?? 'Unbekannt',
                    'factor' => round(abs((float) $tx->amount) / $avg, 1),
                ];
            }
        }

        // Limit large transaction anomalies to top 3
        usort($anomalies, fn ($a, $b) => $b['factor'] <=> $a['factor']);
        $anomalies = array_slice($anomalies, 0, 3);

        // Categories >30% above 3-month rolling average
        $threeMonthAvgByCategory = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) / 3 as avg_monthly'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
            ->where('transactions.date', '>=', $threeMonthsAgo)
            ->whereRaw("strftime('%Y-%m', transactions.date) != ?", [$currentMonth])
            ->groupBy('categories.name')
            ->pluck('avg_monthly', 'name');

        foreach ($categoryExpenses as $cat) {
            $avg = (float) ($threeMonthAvgByCategory[$cat->name] ?? 0);
            $current = (float) $cat->total;
            if ($avg > 0 && $current > $avg * 1.3) {
                $anomalies[] = [
                    'type' => 'category_spike',
                    'category' => $cat->name,
                    'aboveAverage' => round((($current - $avg) / $avg) * 100, 1),
                ];
            }
        }

        // New categories (exist this month but not last month)
        $currentCategoryNames = $categoryExpenses->pluck('name')->toArray();
        $prevCategoryNames = $prevCategoryExpenses->keys()->toArray();
        $newCategories = array_diff($currentCategoryNames, $prevCategoryNames);
        foreach ($newCategories as $name) {
            $anomalies[] = [
                'type' => 'new_category',
                'category' => $name,
            ];
        }

        return $anomalies;
    }

    public function toPromptContext(): string
    {
        $lines = ["Finanzdaten (anonymisiert, alle Werte in Prozent vom Einkommen):\n"];

        $lines[] = 'Monatliche Übersicht (letzte 12 Monate):';
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

        // New sections
        if (! empty($this->budgetUtilization)) {
            $lines[] = "\nBudget-Auslastung (aktueller Monat):";
            foreach ($this->budgetUtilization as $b) {
                $statusLabel = match ($b['status']) {
                    'over' => '⚠ ÜBERSCHREITUNG',
                    'warning' => '⚡ Grenzbereich',
                    default => '✓ Im Plan',
                };
                $lines[] = "  {$b['category']}: {$b['spentPercent']}% verbraucht, Prognose {$b['projectedPercent']}% [{$statusLabel}]";
            }
        }

        if ($this->recurringCoveragePercent > 0) {
            $lines[] = "\nDaueraufträge decken {$this->recurringCoveragePercent}% der Ausgaben ab.";
        }

        if ($this->incomeStability > 0) {
            $stabilityLabel = $this->incomeStability < 10 ? 'sehr stabil' : ($this->incomeStability < 25 ? 'mäßig stabil' : 'schwankend');
            $lines[] = "Einkommensstabilität: {$stabilityLabel} (Variationskoeffizient {$this->incomeStability}%)";
        }

        if (! empty($this->categoryTrends)) {
            $lines[] = "\nKategorie-Trends (12 Monate, % vom Einkommen):";
            foreach ($this->categoryTrends as $trend) {
                $values = implode(', ', array_map(
                    fn ($m) => "{$m['month']}:{$m['percentOfIncome']}%",
                    $trend['months']
                ));
                $lines[] = "  {$trend['category']}: {$values}";
            }
        }

        if (! empty($this->anomalies)) {
            $lines[] = "\nErkannte Auffälligkeiten:";
            foreach ($this->anomalies as $a) {
                match ($a['type']) {
                    'large_transaction' => $lines[] = "  - Große Einzelbuchung in {$a['category']}: {$a['factor']}x über Durchschnitt",
                    'category_spike' => $lines[] = "  - Kategorie {$a['category']}: {$a['aboveAverage']}% über 3-Monats-Durchschnitt",
                    'new_category' => $lines[] = "  - Neue Kategorie: {$a['category']}",
                    default => null,
                };
            }
        }

        return implode("\n", $lines);
    }
}
