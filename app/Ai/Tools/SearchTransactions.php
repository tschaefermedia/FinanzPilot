<?php

namespace App\Ai\Tools;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchTransactions implements Tool
{
    public function description(): string
    {
        return 'Suche Buchungen nach Kategorie, Zeitraum oder Stichwort. Gibt Buchungen als Prozent vom monatlichen Einkommen zurück.';
    }

    public function handle(Request $request): string
    {
        $months = $request['months'] ?? 3;
        $dateFrom = now()->subMonths($months)->startOfMonth()->toDateString();

        $query = Transaction::with('category')
            ->where('date', '>=', $dateFrom)
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'));

        if (! empty($request['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', '%'.$request['category'].'%'));
        }

        if (! empty($request['keyword'])) {
            $query->where('description', 'like', '%'.$request['keyword'].'%');
        }

        // Get monthly income for normalization
        $monthlyIncome = Transaction::selectRaw("strftime('%Y-%m', date) as month, SUM(amount) as income")
            ->where('amount', '>', 0)
            ->where('date', '>=', $dateFrom)
            ->groupByRaw("strftime('%Y-%m', date)")
            ->pluck('income', 'month');

        $transactions = $query->orderByDesc('date')->limit(20)->get();

        $results = $transactions->map(function ($tx) use ($monthlyIncome) {
            $month = substr($tx->date, 0, 7);
            $income = (float) ($monthlyIncome[$month] ?? 0);
            $percentOfIncome = $income > 0 ? round((abs((float) $tx->amount) / $income) * 100, 1) : 0;

            $categoryName = $tx->category?->name ?: 'Ohne';

            return "{$tx->date} | {$categoryName} | {$percentOfIncome}% vom Einkommen | {$tx->description}";
        });

        return $results->isEmpty()
            ? 'Keine Buchungen gefunden.'
            : $results->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string(),
            'keyword' => $schema->string(),
            'months' => $schema->integer()->min(1)->max(12),
        ];
    }
}
