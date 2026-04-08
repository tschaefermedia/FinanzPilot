<?php

namespace App\Ai\Tools;

use App\Models\Loan;
use App\Models\Transaction;
use App\Services\AmortizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetLoanDetails implements Tool
{
    public function description(): string
    {
        return 'Zeigt Details zu Krediten: Fortschritt, monatliche Rate als Prozent vom Einkommen, Typ und Richtung.';
    }

    public function handle(Request $request): string
    {
        $loans = Loan::with('payments')->get();

        if ($loans->isEmpty()) {
            return 'Keine Kredite vorhanden.';
        }

        // Get current monthly income for burden calculation
        $currentIncome = Transaction::where('amount', '>', 0)
            ->whereRaw("strftime('%Y-%m', date) = ?", [now()->subMonth()->format('Y-m')])
            ->sum('amount');

        $amortization = new AmortizationService;
        $lines = [];
        $index = 0;

        foreach ($loans as $loan) {
            $name = ! empty($request['loan_name']) ? $request['loan_name'] : 'Kredit '.chr(65 + $index);
            $summary = $amortization->calculateSummary($loan);
            $monthlyPayment = $summary['monthlyPayment'] ?? 0;
            $burdenPercent = $currentIncome > 0 ? round(($monthlyPayment / $currentIncome) * 100, 1) : 0;
            $type = $loan->type === 'bank' ? 'Bankdarlehen' : 'Informell';
            $direction = $loan->direction === 'owed_by_me' ? 'Schulden' : 'Forderung';

            $lines[] = "{$name}: {$type}, {$direction}, Fortschritt {$summary['progressPercent']}%, Rate {$burdenPercent}% vom Einkommen";
            $index++;
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'loan_name' => $schema->string(),
        ];
    }
}
