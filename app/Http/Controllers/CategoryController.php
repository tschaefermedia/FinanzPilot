<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
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

        $tree = $categories->map(fn ($cat) => $this->mapTreeNode($cat))->toArray();

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

    private function mapTreeNode(Category $category): array
    {
        $node = [
            'key' => $category->id,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'icon' => $category->icon,
                'parent_id' => $category->parent_id,
                'transactionsCount' => $category->transactions_count ?? 0,
            ],
            'children' => [],
        ];

        if ($category->children->isNotEmpty()) {
            $node['children'] = $category->children->map(fn ($child) => $this->mapTreeNode($child))->toArray();
        }

        return $node;
    }
}
