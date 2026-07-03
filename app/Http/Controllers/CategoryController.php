<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('transactions')->orderBy('sort_order')->orderBy('name')])
            ->withCount('transactions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Per-category monthly average: each category's own total (matching the
        // direct Buchungen count) over the months from the first transaction to now.
        $now = now();
        $firstMonth = Transaction::selectRaw("strftime('%Y-%m', MIN(date)) as m")->value('m');
        $monthsElapsed = 1;
        if ($firstMonth) {
            $monthsElapsed = Carbon::createFromFormat('Y-m', $firstMonth)->startOfMonth()->diffInMonths($now->copy()->startOfMonth()) + 1;
        }

        $totals = Transaction::select(
            'category_id',
            DB::raw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expense_total'),
            DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income_total')
        )
            ->whereNotNull('category_id')
            ->where('date', '<=', $now->toDateString())
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $tree = $categories->map(fn ($cat) => $this->mapTreeNode($cat, $totals, $monthsElapsed))->toArray();

        return Inertia::render('Categories/Index', [
            'categories' => $tree,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,transfer',
            'icon' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'budget_monthly' => 'nullable|numeric|min:0',
        ]);

        Category::create($validated);

        return redirect()->back()->with('success', 'Kategorie erstellt.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,transfer',
            'icon' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'budget_monthly' => 'nullable|numeric|min:0',
        ]);

        $category->update($validated);

        return redirect()->back()->with('success', 'Kategorie aktualisiert.');
    }

    public function destroy(Category $category)
    {
        Category::where('parent_id', $category->id)
            ->update(['parent_id' => $category->parent_id]);

        $category->transactions()->update(['category_id' => null]);

        $category->delete();

        return redirect()->back()->with('success', 'Kategorie gelöscht.');
    }

    private function mapTreeNode(Category $category, $totals, int $monthsElapsed): array
    {
        $amount = $category->type === 'income'
            ? (float) ($totals[$category->id]->income_total ?? 0)
            : (float) ($totals[$category->id]->expense_total ?? 0);

        $node = [
            'key' => $category->id,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'icon' => $category->icon,
                'parent_id' => $category->parent_id,
                'budget_monthly' => $category->budget_monthly,
                'transactionsCount' => $category->transactions_count ?? 0,
                'monthlyAverage' => round($amount / $monthsElapsed, 2),
            ],
            'children' => [],
        ];

        if ($category->children->isNotEmpty()) {
            $node['children'] = $category->children->map(fn ($child) => $this->mapTreeNode($child, $totals, $monthsElapsed))->toArray();
        }

        return $node;
    }
}
