<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CategoryAnalysisController extends Controller
{
    public function __invoke(Request $request)
    {
        $now = now()->format('Y-m');
        $selectedMonth = $request->query('month');

        if (! $selectedMonth || ! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = $now;
        }

        $firstMonth = Transaction::selectRaw("strftime('%Y-%m', MIN(date)) as m")->value('m') ?? $now;
        $lastMonth = Transaction::selectRaw("strftime('%Y-%m', MAX(date)) as m")->value('m') ?? $now;
        if ($lastMonth > $now) {
            $lastMonth = $now;
        }

        $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $prevMonth = $selectedMonth > $firstMonth ? $selectedDate->copy()->subMonth()->format('Y-m') : null;
        $nextMonth = $selectedMonth < $lastMonth ? $selectedDate->copy()->addMonth()->format('Y-m') : null;

        $monthStart = $selectedDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedDate->copy()->endOfMonth()->toDateString();

        // Get all category totals for the period
        $categoryTotals = Transaction::select(
            'category_id',
            DB::raw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expense_total'),
            DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income_total'),
            DB::raw('COUNT(*) as transaction_count')
        )
            ->whereNotNull('category_id')
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Cumulative totals up to (and including) the selected month, for the
        // monthly average. Averaged over the months from the first transaction
        // to the selected month.
        $cumulativeTotals = Transaction::select(
            'category_id',
            DB::raw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expense_total'),
            DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income_total')
        )
            ->whereNotNull('category_id')
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'))
            ->where('date', '<=', $monthEnd)
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $monthsElapsed = 1;
        if ($selectedMonth >= $firstMonth) {
            $monthsElapsed = Carbon::createFromFormat('Y-m', $firstMonth)->startOfMonth()->diffInMonths($selectedDate) + 1;
        }

        // Load the full category tree so aggregation works at any nesting depth.
        $allCategories = Category::orderBy('sort_order')->orderBy('name')->get();
        $childrenByParent = $allCategories->groupBy('parent_id');

        $totalExpenses = $categoryTotals->sum('expense_total');
        $totalIncome = $categoryTotals->sum('income_total');

        // Build a full recursive node (own totals + every descendant's), pruning
        // subtrees with no activity. Works at any nesting depth.
        $buildNode = function (Category $category) use (&$buildNode, $categoryTotals, $cumulativeTotals, $monthsElapsed, $childrenByParent, $totalExpenses, $totalIncome): ?array {
            $expense = (float) ($categoryTotals[$category->id]?->expense_total ?? 0);
            $income = (float) ($categoryTotals[$category->id]?->income_total ?? 0);
            $count = (int) ($categoryTotals[$category->id]?->transaction_count ?? 0);
            // Monthly averages are additive across the subtree (constant divisor).
            $avgMonthlyExpense = (float) ($cumulativeTotals[$category->id]?->expense_total ?? 0) / $monthsElapsed;
            $avgMonthlyIncome = (float) ($cumulativeTotals[$category->id]?->income_total ?? 0) / $monthsElapsed;

            $children = [];
            foreach ($childrenByParent[$category->id] ?? [] as $child) {
                $childNode = $buildNode($child);
                if ($childNode !== null) {
                    $expense += $childNode['expense'];
                    $income += $childNode['income'];
                    $count += $childNode['transactionCount'];
                    $avgMonthlyExpense += $childNode['avgMonthlyExpense'];
                    $avgMonthlyIncome += $childNode['avgMonthlyIncome'];
                    $children[] = $childNode;
                }
            }

            if ($expense <= 0 && $income <= 0) {
                return null;
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'expense' => round($expense, 2),
                'income' => round($income, 2),
                'transactionCount' => $count,
                'expensePercent' => $totalExpenses > 0 ? round(($expense / $totalExpenses) * 100, 1) : 0,
                'incomePercent' => $totalIncome > 0 ? round(($income / $totalIncome) * 100, 1) : 0,
                'avgMonthlyExpense' => round($avgMonthlyExpense, 2),
                'avgMonthlyIncome' => round($avgMonthlyIncome, 2),
                'budget' => $category->budget_monthly,
                'children' => $children,
            ];
        };

        $hierarchy = [];
        foreach ($allCategories->whereNull('parent_id') as $root) {
            $node = $buildNode($root);
            if ($node !== null) {
                $hierarchy[] = $node;
            }
        }

        return Inertia::render('Categories/Analysis', [
            'selectedMonth' => $selectedMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'hierarchy' => $hierarchy,
            'totalExpenses' => round($totalExpenses, 2),
            'totalIncome' => round($totalIncome, 2),
        ]);
    }
}
