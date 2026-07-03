<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_monthly_budget(): void
    {
        $this->post('/categories', [
            'name' => 'Lebensmittel',
            'type' => 'expense',
            'budget_monthly' => 450.50,
        ])->assertRedirect();

        $this->assertEqualsWithDelta(450.50, (float) Category::where('name', 'Lebensmittel')->value('budget_monthly'), 0.001);
    }

    public function test_update_persists_monthly_budget(): void
    {
        $category = Category::create(['name' => 'Wohnen', 'type' => 'expense']);

        $this->put("/categories/{$category->id}", [
            'name' => 'Wohnen',
            'type' => 'expense',
            'budget_monthly' => 1200,
        ])->assertRedirect();

        $this->assertEqualsWithDelta(1200.0, (float) $category->fresh()->budget_monthly, 0.001);
    }
}
