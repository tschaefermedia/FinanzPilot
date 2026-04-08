<?php

namespace App\Ai\Agents;

use App\Models\AiInsight;
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
#[Timeout(120)]
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
- Wenn eine vorherige Analyse existiert, vergleiche die aktuelle Situation damit
- Wenn der aktuelle Monat als UNVOLLSTÄNDIG markiert ist, ignoriere dessen Werte für den healthScore und die Trendberechnung — das Gehalt kommt am Monatsende, daher sind die Werte nicht repräsentativ. Bewerte nur abgeschlossene Monate.

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

        // Include previous analysis for comparison
        $previous = AiInsight::latest()->first();
        if ($previous && $previous->structured_data) {
            $prevScore = $previous->health_score ?? '?';
            $prevTrend = $previous->health_trend ?? '?';
            $prevSummary = $previous->structured_data['summary'] ?? '';
            $prevDate = $previous->created_at->format('d.m.Y');
            $base .= "\n\n--- VORHERIGE ANALYSE ({$prevDate}) ---\n";
            $base .= "Health Score: {$prevScore}, Trend: {$prevTrend}\n";
            $base .= "Zusammenfassung: {$prevSummary}";
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

        $agent = (new self)->withSnapshot($snapshot);

        $response = $agent->prompt(
            'Analysiere die Finanzdaten.',
            model: AiConfigService::model(),
        );

        $structured = self::normalize($response->toArray());

        // Store in history
        AiInsight::create([
            'health_score' => $structured['healthScore'] ?? null,
            'health_trend' => $structured['healthTrend'] ?? null,
            'structured_data' => $structured,
            'snapshot_hash' => $snapshot->hash,
            'provider' => AiConfigService::providerDisplayName(),
        ]);

        return [
            'structured' => $structured,
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
                'currentMonthComplete' => $snapshot->currentMonthComplete,
            ],
        ];
    }

    /**
     * Get past analyses for the history timeline.
     */
    public static function history(int $limit = 10): array
    {
        return AiInsight::latest()
            ->limit($limit)
            ->get()
            ->map(fn (AiInsight $insight) => [
                'id' => $insight->id,
                'healthScore' => $insight->health_score,
                'healthTrend' => $insight->health_trend,
                'summary' => $insight->structured_data['summary'] ?? null,
                'highlights' => $insight->structured_data['highlights'] ?? [],
                'provider' => $insight->provider,
                'createdAt' => $insight->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Normalize AI response to ensure consistent structure regardless of model quality.
     * Some models (e.g. Ollama/Gemma) return plain strings instead of objects for arrays.
     */
    private static function normalize(array $data): array
    {
        // Normalize highlights: string → {type: 'warning', title: first sentence, detail: full text}
        if (isset($data['highlights']) && is_array($data['highlights'])) {
            $data['highlights'] = array_map(function ($h) {
                if (is_string($h)) {
                    return ['type' => 'warning', 'title' => self::extractTitle($h), 'detail' => $h];
                }
                if (is_array($h) && ! isset($h['type'])) {
                    return ['type' => 'warning', 'title' => $h['title'] ?? '', 'detail' => $h['detail'] ?? ''];
                }

                return $h;
            }, $data['highlights']);
        }

        // Normalize categoryInsights: string → {category: string, trend: 'stable', comment: ''}
        if (isset($data['categoryInsights']) && is_array($data['categoryInsights'])) {
            $data['categoryInsights'] = array_values(array_filter(array_map(function ($c) {
                if (is_string($c)) {
                    return ['category' => $c, 'trend' => 'stable', 'comment' => ''];
                }
                if (is_array($c) && ! isset($c['category'])) {
                    return null;
                }

                return $c;
            }, $data['categoryInsights'])));
        }

        // Normalize recommendations: string → {priority: 'medium', title: first sentence, detail: full text}
        if (isset($data['recommendations']) && is_array($data['recommendations'])) {
            $data['recommendations'] = array_map(function ($r) {
                if (is_string($r)) {
                    return ['priority' => 'medium', 'title' => self::extractTitle($r), 'detail' => $r, 'impact' => ''];
                }
                if (is_array($r) && ! isset($r['priority'])) {
                    return ['priority' => 'medium', 'title' => $r['title'] ?? '', 'detail' => $r['detail'] ?? '', 'impact' => $r['impact'] ?? ''];
                }

                return $r;
            }, $data['recommendations']);
        }

        // Normalize loanInsights: flatten nested objects → {loan: string, comment: string}
        if (isset($data['loanInsights']) && is_array($data['loanInsights'])) {
            $normalized = [];
            foreach ($data['loanInsights'] as $l) {
                if (is_array($l) && isset($l['loan']) && isset($l['comment'])) {
                    $normalized[] = $l;
                } elseif (is_array($l)) {
                    // Flatten {Kredit A: {Fortschritt: "19.5%"}, Kredit B: ...} format
                    foreach ($l as $name => $info) {
                        $comment = is_array($info) ? implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($info), array_values($info))) : (string) $info;
                        $normalized[] = ['loan' => $name, 'comment' => $comment];
                    }
                } elseif (is_string($l)) {
                    $normalized[] = ['loan' => $l, 'comment' => ''];
                }
            }
            $data['loanInsights'] = self::mergeAlternatingLoanInsights($normalized);
        }

        return $data;
    }

    /**
     * Merge alternating key/value loan entries from local models.
     * Input:  [{loan:"Kredit",comment:"Kredit A"},{loan:"Fortschritt",comment:"19.5%"},...]
     * Output: [{loan:"Kredit A",comment:"Fortschritt: 19.5%"},...]
     */
    private static function mergeAlternatingLoanInsights(array $insights): array
    {
        // Detect alternating pattern: entries where comment matches "Kredit [A-Z]"
        $hasNameEntries = false;
        foreach ($insights as $entry) {
            if (preg_match('/^Kredit [A-Z]$/', $entry['comment'] ?? '')) {
                $hasNameEntries = true;
                break;
            }
        }

        if (! $hasNameEntries) {
            return $insights;
        }

        $merged = [];
        $currentLoan = null;
        $currentDetails = [];

        foreach ($insights as $entry) {
            $comment = $entry['comment'] ?? '';
            if (preg_match('/^Kredit [A-Z]$/', $comment)) {
                // Flush previous loan
                if ($currentLoan !== null) {
                    $merged[] = ['loan' => $currentLoan, 'comment' => implode(', ', $currentDetails) ?: ''];
                }
                $currentLoan = $comment;
                $currentDetails = [];
            } elseif ($currentLoan !== null) {
                $key = $entry['loan'] ?? '';
                $val = $comment;
                $currentDetails[] = $key ? "{$key}: {$val}" : $val;
            } else {
                $merged[] = $entry;
            }
        }

        // Flush last loan
        if ($currentLoan !== null) {
            $merged[] = ['loan' => $currentLoan, 'comment' => implode(', ', $currentDetails) ?: ''];
        }

        return $merged;
    }

    /**
     * Extract a short title from a longer text (first sentence or clause).
     */
    private static function extractTitle(string $text): string
    {
        // Try splitting on first period followed by space
        if (preg_match('/^(.{20,80}?)\.\s/', $text, $m)) {
            return $m[1].'.';
        }

        // Try splitting on first comma followed by space (if short enough)
        if (preg_match('/^(.{15,60}?),\s/', $text, $m)) {
            return $m[1];
        }

        // Fallback: truncate at word boundary
        if (mb_strlen($text) > 70) {
            $truncated = mb_substr($text, 0, 70);
            $lastSpace = mb_strrpos($truncated, ' ');

            return $lastSpace ? mb_substr($truncated, 0, $lastSpace).'...' : $truncated.'...';
        }

        return $text;
    }
}
