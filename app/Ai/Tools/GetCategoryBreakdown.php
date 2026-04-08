<?php

namespace App\Ai\Tools;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCategoryBreakdown implements Tool
{
    public function description(): string
    {
        return 'Zeigt die Ausgabenverteilung nach Kategorien für einen bestimmten Monat als Prozent der Gesamtausgaben.';
    }

    public function handle(Request $request): string
    {
        $month = $request['month'] ?? now()->subMonth()->format('Y-m');

        $categories = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
            ->whereRaw("strftime('%Y-%m', transactions.date) = ?", [$month])
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        if ($categories->isEmpty()) {
            return "Keine Ausgaben für {$month} gefunden.";
        }

        $total = $categories->sum('total');
        $lines = ["Ausgabenverteilung {$month}:"];
        foreach ($categories as $cat) {
            $percent = $total > 0 ? round(($cat->total / $total) * 100, 1) : 0;
            $lines[] = "  {$cat->name}: {$percent}%";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->string(),
        ];
    }
}
