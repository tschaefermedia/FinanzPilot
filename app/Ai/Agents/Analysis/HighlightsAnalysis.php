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
#[Temperature(0.3)]
#[Timeout(120)]
class HighlightsAnalysis implements Agent, HasStructuredOutput
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
Du identifizierst 2-4 bemerkenswerte Beobachtungen in den Finanzdaten.

Regeln:
- Jeder Eintrag hat type (positive/warning/critical), title (kurz), detail (1-2 Sätze)
- Mindestens 1 positive Beobachtung wenn möglich
- Nur Prozentangaben, keine Euro-Beträge
- Fokussiere auf Trends und Veränderungen
- Unvollständige Monate ignorieren

Beispiel-Antwort:
{"highlights": [{"type": "positive", "title": "Stabile Fixkosten", "detail": "Die Fixkosten sind seit 3 Monaten konstant bei 45% des Einkommens."}, {"type": "warning", "title": "Steigende Lebensmittelkosten", "detail": "Die Kategorie Lebensmittel ist um 25% gegenüber dem Vormonat gestiegen."}]}
INSTRUCTIONS;

        $base .= "\n\n--- FINANZDATEN ---\n".$this->context;

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'highlights' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['positive', 'warning', 'critical'])->required(),
                'title' => $schema->string()->required(),
                'detail' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    public static function run(FinancialSnapshot $snapshot, ?string $model): array
    {
        $agent = (new self)->withContext($snapshot->toHighlightsContext());
        $response = $agent->prompt('Identifiziere 2-4 bemerkenswerte Beobachtungen.', model: $model);

        return $response->toArray()['highlights'] ?? [];
    }
}
