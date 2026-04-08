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
class HealthScoreAnalysis implements Agent, HasStructuredOutput
{
    use Promptable;

    private string $context = '';

    private string $previousSummary = '';

    public function withContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function withPreviousSummary(string $summary): self
    {
        $this->previousSummary = $summary;

        return $this;
    }

    public function instructions(): string
    {
        $base = <<<'INSTRUCTIONS'
Du bewertest die finanzielle Gesundheit eines Nutzers.

Regeln:
- healthScore: 80+ = exzellent, 60-79 = gut, 40-59 = verbesserungswürdig, <40 = kritisch
- Basiere den Score auf abgeschlossene Monate, NICHT auf unvollständige
- healthTrend: improving/stable/declining basierend auf den letzten 3 abgeschlossenen Monaten
- summary: 2-3 Sätze Gesamteinschätzung, nur Prozentangaben, keine Euro-Beträge
- Sei direkt und konkret

Beispiel-Antwort:
{"healthScore": 68, "healthTrend": "stable", "summary": "Die Sparquote liegt bei 4.1%, was auf ein solides Fundament hindeutet. Die Ausgaben sind in den letzten Monaten leicht gestiegen."}
INSTRUCTIONS;

        $base .= "\n\n--- FINANZDATEN ---\n".$this->context;

        if ($this->previousSummary) {
            $base .= "\n\n--- VORHERIGE ANALYSE ---\n".$this->previousSummary;
        }

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'healthScore' => $schema->integer()->min(0)->max(100)->required(),
            'healthTrend' => $schema->string()->enum(['improving', 'stable', 'declining'])->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public static function run(FinancialSnapshot $snapshot, ?string $model, string $previousSummary = ''): array
    {
        $agent = (new self)
            ->withContext($snapshot->toHealthContext())
            ->withPreviousSummary($previousSummary);

        $response = $agent->prompt('Bewerte die finanzielle Gesundheit.', model: $model);

        return $response->toArray();
    }
}
