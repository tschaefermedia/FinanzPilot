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
class CategoryAnalysis implements Agent, HasStructuredOutput
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
Du analysierst die Top 3-5 Ausgabenkategorien.

Regeln:
- Jeder Eintrag hat category (Name), trend (rising/stable/falling), comment (1 Satz)
- comment MUSS ausgefüllt sein — beschreibe die Entwicklung oder Auffälligkeit
- Nur Prozentangaben, keine Euro-Beträge
- Fokussiere auf Veränderungen über die letzten Monate

Beispiel-Antwort:
{"categoryInsights": [{"category": "Miete", "trend": "stable", "comment": "Konstant bei 18% des Einkommens über die letzten 4 Monate."}, {"category": "Lebensmittel", "trend": "rising", "comment": "Von 4% auf 7% des Einkommens gestiegen in den letzten 3 Monaten."}]}
INSTRUCTIONS;

        $base .= "\n\n--- FINANZDATEN ---\n".$this->context;

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'categoryInsights' => $schema->array()->items($schema->object([
                'category' => $schema->string()->required(),
                'trend' => $schema->string()->enum(['rising', 'stable', 'falling'])->required(),
                'comment' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    public static function run(FinancialSnapshot $snapshot, ?string $model): array
    {
        $agent = (new self)->withContext($snapshot->toCategoryContext());
        $response = $agent->prompt('Analysiere die Top-Kategorien.', model: $model);

        return $response->toArray()['categoryInsights'] ?? [];
    }
}
