<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function __invoke(Request $request)
    {
        $month = $request->query('month');
        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Expense total per category for the month (positive numbers).
        $spendByCategory = Transaction::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('amount', '<', 0)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = Category::get(['id', 'name', 'icon', 'parent_id', 'budget_monthly']);
        $childrenMap = $categories->groupBy('parent_id');
        $byId = $categories->keyBy('id');

        // Full "Parent › Child" path so same-named nested categories are distinguishable.
        $pathName = function ($category) use ($byId): string {
            $parts = [$category->name];
            $parent = $category->parent_id ? $byId->get($category->parent_id) : null;
            while ($parent) {
                array_unshift($parts, $parent->name);
                $parent = $parent->parent_id ? $byId->get($parent->parent_id) : null;
            }

            return implode(' › ', $parts);
        };

        // Spend for a category rolls up its whole subtree.
        $subtreeSpend = function (int $id) use (&$subtreeSpend, $spendByCategory, $childrenMap): float {
            $total = (float) ($spendByCategory[$id] ?? 0);
            foreach ($childrenMap[$id] ?? [] as $child) {
                $total += $subtreeSpend($child->id);
            }

            return $total;
        };

        $budgets = $categories
            ->filter(fn ($c) => $c->budget_monthly > 0)
            ->map(function ($c) use ($subtreeSpend, $pathName) {
                $budget = (float) $c->budget_monthly;
                $spent = round($subtreeSpend($c->id), 2);

                return [
                    'id' => $c->id,
                    'name' => $pathName($c),
                    'icon' => $c->icon,
                    'budget' => $budget,
                    'spent' => $spent,
                    'remaining' => round($budget - $spent, 2),
                    'percent' => $budget > 0 ? (int) round($spent / $budget * 100) : 0,
                ];
            })
            ->sortByDesc('percent')
            ->values();

        return Inertia::render('Budgets/Index', [
            'month' => $month,
            'budgets' => $budgets,
            'totals' => [
                'budget' => round($budgets->sum('budget'), 2),
                'spent' => round($budgets->sum('spent'), 2),
                'remaining' => round($budgets->sum('remaining'), 2),
            ],
            'unbudgeted' => $categories
                ->filter(fn ($c) => ! ($c->budget_monthly > 0))
                ->map(fn ($c) => ['id' => $c->id, 'name' => $pathName($c)])
                ->sortBy('name')
                ->values(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'budget_monthly' => 'nullable|numeric|min:0',
        ]);

        $category->update(['budget_monthly' => $validated['budget_monthly'] ?: null]);

        return redirect()->back()->with('success', 'Budget aktualisiert.');
    }
}
