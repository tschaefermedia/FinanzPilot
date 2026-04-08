<?php

namespace App\Ai\Tools;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetBudgetStatus implements Tool
{
    public function description(): string
    {
        return 'Zeigt die aktuelle Budget-Auslastung aller Kategorien mit Budgets.';
    }

    public function handle(Request $request): string
    {
        $categories = Category::whereNotNull('budget_monthly')
            ->where('budget_monthly', '>', 0);

        if (! empty($request['category'])) {
            $categories->where('name', 'like', '%'.$request['category'].'%');
        }

        $categories = $categories->get();

        if ($categories->isEmpty()) {
            return 'Keine Budgets konfiguriert.';
        }

        $currentMonth = now()->format('Y-m');
        $dayOfMonth = now()->day;
        $daysInMonth = now()->daysInMonth;

        $lines = [];
        foreach ($categories as $category) {
            $spent = Transaction::where('category_id', $category->id)
                ->where('amount', '<', 0)
                ->whereRaw("strftime('%Y-%m', date) = ?", [$currentMonth])
                ->sum(DB::raw('ABS(amount)'));

            $budget = (float) $category->budget_monthly;
            $spentPercent = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;
            $projected = $dayOfMonth > 0 ? round(($spent / $dayOfMonth) * $daysInMonth, 2) : 0;
            $projectedPercent = $budget > 0 ? round(($projected / $budget) * 100, 1) : 0;
            $status = $projectedPercent > 110 ? 'ÜBERSCHREITUNG' : ($projectedPercent > 90 ? 'Grenzbereich' : 'Im Plan');

            $lines[] = "{$category->name}: {$spentPercent}% verbraucht, Prognose {$projectedPercent}% [{$status}]";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string(),
        ];
    }
}
