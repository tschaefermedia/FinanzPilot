<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_spend_rolls_up_child_categories(): void
    {
        $parent = Category::create(['name' => 'Wohnen', 'type' => 'expense', 'budget_monthly' => 1000]);
        $child = Category::create(['name' => 'Strom', 'type' => 'expense', 'parent_id' => $parent->id]);

        // Spend recorded on the child should count against the parent's budget.
        Transaction::create(['date' => '2026-07-10', 'amount' => -200, 'description' => 'Miete', 'category_id' => $parent->id]);
        Transaction::create(['date' => '2026-07-12', 'amount' => -80, 'description' => 'Stromabschlag', 'category_id' => $child->id]);
        // Different month — must be ignored.
        Transaction::create(['date' => '2026-06-01', 'amount' => -500, 'description' => 'Alt', 'category_id' => $parent->id]);

        $this->get('/budgets?month=2026-07')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Index', false)
                ->where('budgets.0.name', 'Wohnen')
                ->where('budgets.0.spent', 280)
                ->where('budgets.0.remaining', 720)
                ->where('totals.spent', 280)
            );
    }

    public function test_update_sets_and_clears_budget(): void
    {
        $category = Category::create(['name' => 'Essen', 'type' => 'expense']);

        $this->put("/budgets/{$category->id}", ['budget_monthly' => 300])->assertRedirect();
        $this->assertEquals(300, $category->fresh()->budget_monthly);

        $this->put("/budgets/{$category->id}", ['budget_monthly' => null])->assertRedirect();
        $this->assertNull($category->fresh()->budget_monthly);
    }
}
