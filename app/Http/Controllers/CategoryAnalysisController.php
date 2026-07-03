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

        // Load the full category tree so aggregation works at any nesting depth.
        $allCategories = Category::orderBy('sort_order')->orderBy('name')->get();
        $childrenByParent = $allCategories->groupBy('parent_id');

        $totalExpenses = $categoryTotals->sum('expense_total');
        $totalIncome = $categoryTotals->sum('income_total');

        // Sum a category's own totals plus every descendant's, recursively.
        $subtreeTotals = function (Category $category) use (&$subtreeTotals, $categoryTotals, $childrenByParent): array {
            $expense = (float) ($categoryTotals[$category->id]?->expense_total ?? 0);
            $income = (float) ($categoryTotals[$category->id]?->income_total ?? 0);
            $count = (int) ($categoryTotals[$category->id]?->transaction_count ?? 0);

            foreach ($childrenByParent[$category->id] ?? [] as $child) {
                $childTotals = $subtreeTotals($child);
                $expense += $childTotals['expense'];
                $income += $childTotals['income'];
                $count += $childTotals['count'];
            }

            return ['expense' => $expense, 'income' => $income, 'count' => $count];
        };

        $hierarchy = [];
        $treemapData = [];

        foreach ($allCategories->whereNull('parent_id') as $parent) {
            $parentTotals = $subtreeTotals($parent);
            $parentExpense = $parentTotals['expense'];
            $parentIncome = $parentTotals['income'];
            $parentTxCount = $parentTotals['count'];

            $children = [];
            foreach ($childrenByParent[$parent->id] ?? [] as $child) {
                $childTotals = $subtreeTotals($child);
                $childExpense = $childTotals['expense'];
                $childIncome = $childTotals['income'];
                $childTxCount = $childTotals['count'];

                if ($childExpense > 0 || $childIncome > 0) {
                    $children[] = [
                        'id' => $child->id,
                        'name' => $child->name,
                        'type' => $child->type,
                        'expense' => round($childExpense, 2),
                        'income' => round($childIncome, 2),
                        'transactionCount' => $childTxCount,
                        'expensePercent' => $totalExpenses > 0 ? round(($childExpense / $totalExpenses) * 100, 1) : 0,
                        'incomePercent' => $totalIncome > 0 ? round(($childIncome / $totalIncome) * 100, 1) : 0,
                        'budget' => $child->budget_monthly,
                    ];
                }
            }

            if ($parentExpense > 0 || $parentIncome > 0) {
                $hierarchy[] = [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'type' => $parent->type,
                    'expense' => round($parentExpense, 2),
                    'income' => round($parentIncome, 2),
                    'transactionCount' => $parentTxCount,
                    'expensePercent' => $totalExpenses > 0 ? round(($parentExpense / $totalExpenses) * 100, 1) : 0,
                    'incomePercent' => $totalIncome > 0 ? round(($parentIncome / $totalIncome) * 100, 1) : 0,
                    'budget' => $parent->budget_monthly,
                    'children' => $children,
                ];

                if ($parentExpense > 0) {
                    $treemapData[] = [
                        'x' => $parent->name,
                        'y' => round($parentExpense, 2),
                    ];
                }
            }
        }

        usort($treemapData, fn ($a, $b) => $b['y'] <=> $a['y']);

        return Inertia::render('Categories/Analysis', [
            'selectedMonth' => $selectedMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'hierarchy' => $hierarchy,
            'treemapData' => $treemapData,
            'totalExpenses' => round($totalExpenses, 2),
            'totalIncome' => round($totalIncome, 2),
        ]);
    }
}
