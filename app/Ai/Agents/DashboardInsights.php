<?php

namespace App\Ai\Agents;

use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[MaxTokens(512)]
#[Temperature(0.3)]
#[Timeout(30)]
class DashboardInsights implements Agent
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
Du bist der Finanzassistent von FinanzPilot.

Regeln:
- Maximal 3 konkrete Empfehlungen
- Formatiere jede Empfehlung als: **Fettgedruckter Titel** — 1-2 Sätze Erklärung
- Nenne nur Prozentangaben — niemals absolute Beträge oder Euro-Werte
- Verwende keine echten Namen — die Daten sind anonymisiert
- Empfehle keine externen Tools oder Apps
- Fokussiere auf Trends und Veränderungen, nicht auf statische Zahlen
- Sei direkt und konkret, keine allgemeinen Spartipps
- Antworte in 100-150 Wörtern
INSTRUCTIONS;

        if ($this->snapshot) {
            $base .= "\n\n--- FINANZDATEN ---\n".$this->snapshot->toPromptContext();
        }

        return $base;
    }

    /**
     * Get compact dashboard insights.
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
            ? $agent->model($model)->prompt('Gib eine kurze Finanzanalyse.')
            : $agent->prompt('Gib eine kurze Finanzanalyse.');

        return [
            'insights' => $response->text,
            'provider' => AiConfigService::providerDisplayName(),
            'generatedAt' => now()->toIso8601String(),
            'snapshotHash' => $snapshot->hash,
        ];
    }
}
