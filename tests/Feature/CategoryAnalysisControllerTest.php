<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_page_loads(): void
    {
        $response = $this->get('/categories/analysis');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Categories/Analysis', false));
    }

    public function test_hierarchical_aggregation(): void
    {
        $parent = Category::create(['name' => 'Wohnen', 'type' => 'expense']);
        $child1 = Category::create(['name' => 'Miete', 'type' => 'expense', 'parent_id' => $parent->id]);
        $child2 = Category::create(['name' => 'Strom', 'type' => 'expense', 'parent_id' => $parent->id]);

        Transaction::create([
            'date' => now()->format('Y-m-d'),
            'amount' => -800,
            'description' => 'Miete',
            'category_id' => $child1->id,
        ]);
        Transaction::create([
            'date' => now()->format('Y-m-d'),
            'amount' => -100,
            'description' => 'Strom',
            'category_id' => $child2->id,
        ]);

        $response = $this->get('/categories/analysis');

        $response->assertInertia(fn ($page) => $page
            ->has('hierarchy', 1)
            ->where('hierarchy.0.name', 'Wohnen')
            ->where('hierarchy.0.expense', 900)
            ->has('hierarchy.0.children', 2)
        );
    }

    public function test_month_filter(): void
    {
        $category = Category::create(['name' => 'Test', 'type' => 'expense']);

        Transaction::create([
            'date' => '2026-01-15',
            'amount' => -50,
            'description' => 'Jan',
            'category_id' => $category->id,
        ]);
        Transaction::create([
            'date' => '2026-02-15',
            'amount' => -100,
            'description' => 'Feb',
            'category_id' => $category->id,
        ]);

        $response = $this->get('/categories/analysis?month=2026-01');

        $response->assertInertia(fn ($page) => $page
            ->where('selectedMonth', '2026-01')
            ->where('hierarchy.0.expense', 50)
        );
    }
}
