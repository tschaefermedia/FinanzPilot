<?php

namespace App\Ai\Agents;

use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[MaxTokens(3000)]
#[Temperature(0.3)]
#[Timeout(45)]
class FinancialAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    private ?FinancialSnapshot $snapshot = null;

    public function withSnapshot(FinancialSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function instructions(): string
    {
        $base = <<<'INSTRUCTIONS'
Du bist der Finanzassistent von FinanzPilot. Der Nutzer verwaltet hier Konten, Buchungen, Kategorien und Kredite.

FinanzPilot bietet diese Funktionen:
- Übersicht (Dashboard mit Monatsvergleich und Diagrammen)
- Konten (Kontoverwaltung und Salden)
- Buchungen (Transaktionsliste mit Filterung)
- Kategorien (hierarchische Kategorien mit optionalen Monatsbudgets)
- Import (CSV-Import von Bankauszügen)
- Darlehen (Kreditverwaltung mit Tilgungsplan und Zahlungszuordnung)
- Daueraufträge (wiederkehrende Buchungen)
- Export (Excel-Export für Steuerberater)
- KI-Analyse (diese Seite — Finanzübersicht, Trends, Empfehlungen)

Regeln:
- Nenne nur Prozentangaben — niemals absolute Beträge oder Euro-Werte
- Beziehe dich ausschließlich auf die oben gelisteten Funktionen — erfinde keine neuen
- Verwende keine echten Namen aus den Daten — die Daten sind anonymisiert (z.B. "Kredit A")
- Empfehle keine externen Tools oder Apps
- Fokussiere auf Trends und Veränderungen über die letzten 12 Monate, nicht auf statische Zahlen
- Sei direkt und konkret, keine allgemeinen Spartipps
- Beachte Budget-Auslastung, Einkommensstabilität und Auffälligkeiten in deiner Analyse

Regeln für die Antwort:
- highlights: 2-4 Einträge, mindestens 1 positive und 1 warning/critical wenn zutreffend
- categoryInsights: Top 3-5 Kategorien nach Relevanz
- recommendations: 2-4 konkrete, priorisierte Empfehlungen
- loanInsights: nur wenn Kredite existieren, sonst leeres Array
- healthScore: 80+ = exzellent, 60-79 = gut, 40-59 = verbesserungswürdig, <40 = kritisch
INSTRUCTIONS;

        if ($this->snapshot) {
            $base .= "\n\n--- FINANZDATEN ---\n".$this->snapshot->toPromptContext();
        }

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'healthScore' => $schema->integer()->min(0)->max(100)->required(),
            'healthTrend' => $schema->string()->enum(['improving', 'stable', 'declining'])->required(),
            'summary' => $schema->string()->required(),
            'highlights' => $schema->array($schema->object([
                'type' => $schema->string()->enum(['positive', 'warning', 'critical'])->required(),
                'title' => $schema->string()->required(),
                'detail' => $schema->string()->required(),
            ]))->required(),
            'categoryInsights' => $schema->array($schema->object([
                'category' => $schema->string()->required(),
                'trend' => $schema->string()->enum(['rising', 'stable', 'falling'])->required(),
                'comment' => $schema->string()->required(),
            ])),
            'recommendations' => $schema->array($schema->object([
                'priority' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                'title' => $schema->string()->required(),
                'detail' => $schema->string()->required(),
                'impact' => $schema->string(),
            ]))->required(),
            'loanInsights' => $schema->array($schema->object([
                'loan' => $schema->string()->required(),
                'comment' => $schema->string()->required(),
            ])),
        ];
    }

    /**
     * Run the analysis and return structured results.
     */
    public static function analyze(): ?array
    {
        if (! AiConfigService::configure()) {
            return null;
        }

        $snapshot = FinancialSnapshot::capture();
        if (empty($snapshot->monthlyRatios)) {
            return null;
        }

        $model = AiConfigService::model();
        $agent = (new self)->withSnapshot($snapshot);

        $response = $model
            ? $agent->model($model)->prompt('Analysiere die Finanzdaten.')
            : $agent->prompt('Analysiere die Finanzdaten.');

        return [
            'structured' => $response->toArray(),
            'provider' => AiConfigService::providerDisplayName(),
            'generatedAt' => now()->toIso8601String(),
            'snapshotHash' => $snapshot->hash,
            'snapshot' => [
                'monthlyRatios' => $snapshot->monthlyRatios,
                'savingsRate' => $snapshot->savingsRate,
                'savingsRateTrend' => $snapshot->savingsRateTrend,
                'budgetUtilization' => $snapshot->budgetUtilization,
                'recurringCoveragePercent' => $snapshot->recurringCoveragePercent,
                'incomeStability' => $snapshot->incomeStability,
                'anomalies' => $snapshot->anomalies,
                'categoryTrends' => $snapshot->categoryTrends,
            ],
        ];
    }
}
