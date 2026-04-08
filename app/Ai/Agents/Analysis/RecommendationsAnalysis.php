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

#[MaxTokens(768)]
#[Temperature(0.4)]
#[Timeout(120)]
class RecommendationsAnalysis implements Agent, HasStructuredOutput
{
    use Promptable;

    private string $context = '';

    private int $healthScore = 50;

    public function withContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function withHealthScore(int $score): self
    {
        $this->healthScore = $score;

        return $this;
    }

    public function instructions(): string
    {
        $base = <<<INSTRUCTIONS
Du gibst 2-4 konkrete, priorisierte Empfehlungen.

Aktueller Health Score: {$this->healthScore}/100

Regeln:
- Jeder Eintrag hat priority (high/medium/low), title (kurz), detail (1-2 Sätze konkreter Vorschlag), impact (geschätzter Effekt)
- Priorisiere nach Auswirkung: high = sofortige Handlung nötig, medium = sinnvolle Verbesserung, low = langfristige Optimierung
- Nur Prozentangaben, keine Euro-Beträge
- Beziehe dich auf FinanzPilot-Funktionen: Kategorien (Budgets setzen), Daueraufträge, Buchungen prüfen, Darlehen
- Keine externen Tools empfehlen

Beispiel-Antwort:
{"recommendations": [{"priority": "high", "title": "Budgets für Top-Kategorien setzen", "detail": "Die Kategorien Lebensmittel und Sonstiges haben keine Budgets. Setze monatliche Budgets um Überschreitungen frühzeitig zu erkennen.", "impact": "Potenzielle Einsparung von 5-10% der Gesamtausgaben"}]}
INSTRUCTIONS;

        $base .= "\n\n--- FINANZDATEN ---\n".$this->context;

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recommendations' => $schema->array()->items($schema->object([
                'priority' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                'title' => $schema->string()->required(),
                'detail' => $schema->string()->required(),
                'impact' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    public static function run(FinancialSnapshot $snapshot, array $health, ?string $model): array
    {
        $agent = (new self)
            ->withContext($snapshot->toPromptContext())
            ->withHealthScore($health['healthScore'] ?? 50);

        $response = $agent->prompt('Gib 2-4 konkrete Empfehlungen.', model: $model);

        return $response->toArray()['recommendations'] ?? [];
    }
}
