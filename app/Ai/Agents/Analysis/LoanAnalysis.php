<?php

namespace App\Ai\Agents\Analysis;

use App\Services\AI\FinancialSnapshot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[MaxTokens(512)]
#[Temperature(0.3)]
#[Timeout(120)]
class LoanAnalysis implements Agent, HasStructuredOutput
{
    use Promptable;

    private string $context = '';

    public function withContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function instructions(): string
    {
        $base = <<<'INSTRUCTIONS'
Du bewertest den Status jedes Kredits.

Regeln:
- Jeder Eintrag hat loan (anonymisierter Name z.B. "Kredit A") und comment (1-2 Sätze Status/Bewertung)
- comment MUSS ausgefüllt sein — beschreibe Fortschritt und monatliche Belastung
- Nur Prozentangaben, keine Euro-Beträge
- Verwende die anonymisierten Namen aus den Daten

Beispiel-Antwort:
{"loanInsights": [{"loan": "Kredit A", "comment": "Mit 88% Fortschritt fast abbezahlt. Die monatliche Rate von 5% des Einkommens ist tragbar."}, {"loan": "Kredit B", "comment": "Erst 19% getilgt bei einer Rate von 12% des Einkommens. Dies ist die größte Einzelbelastung."}]}
INSTRUCTIONS;

        $base .= "\n\n--- KREDITDATEN ---\n".$this->context;

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'loanInsights' => $schema->array()->items($schema->object([
                'loan' => $schema->string()->required(),
                'comment' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    public static function run(FinancialSnapshot $snapshot, ?string $model): array
    {
        $agent = (new self)->withContext($snapshot->toLoanContext());
        $response = $agent->prompt('Bewerte den Status jedes Kredits.', model: $model);

        return $response->toArray()['loanInsights'] ?? [];
    }
}
