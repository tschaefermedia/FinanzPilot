<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AiInsightsService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
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
- Antworte ausschließlich als gültiges JSON — kein Markdown, kein Fließtext

Antworte exakt in diesem JSON-Format:
{
  "healthScore": <0-100, Gesamtbewertung der finanziellen Gesundheit>,
  "healthTrend": "<improving|stable|declining>",
  "summary": "<1-2 Sätze Gesamteinschätzung>",
  "highlights": [
    { "type": "<positive|warning|critical>", "title": "<kurzer Titel>", "detail": "<1-2 Sätze, nur Prozentangaben>" }
  ],
  "categoryInsights": [
    { "category": "<Name>", "trend": "<rising|stable|falling>", "comment": "<1 Satz>" }
  ],
  "recommendations": [
    { "priority": "<high|medium|low>", "title": "<kurzer Titel>", "detail": "<1-2 Sätze konkreter Vorschlag>", "impact": "<geschätzter Effekt in %>" }
  ],
  "loanInsights": [
    { "loan": "<Kredit X>", "comment": "<1 Satz>" }
  ]
}

Regeln für die JSON-Antwort:
- highlights: 2-4 Einträge, mindestens 1 positive und 1 warning/critical wenn zutreffend
- categoryInsights: Top 3-5 Kategorien nach Relevanz
- recommendations: 2-4 konkrete, priorisierte Empfehlungen
- loanInsights: nur wenn Kredite existieren, sonst leeres Array
- healthScore: 80+ = exzellent, 60-79 = gut, 40-59 = verbesserungswürdig, <40 = kritisch
PROMPT;

    /** @deprecated Use getStructuredInsights() for the new page */
    private const LEGACY_SYSTEM_PROMPT = <<<'PROMPT'
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

Regeln:
- Maximal 3 konkrete Empfehlungen
- Formatiere jede Empfehlung als: **Fettgedruckter Titel** — 1-2 Sätze Erklärung
- Nenne nur Prozentangaben — niemals absolute Beträge oder Euro-Werte
- Beziehe dich ausschließlich auf die oben gelisteten Funktionen — erfinde keine neuen
- Verwende keine echten Namen aus den Daten — die Daten sind anonymisiert (z.B. "Kredit A")
- Empfehle keine externen Tools oder Apps
- Fokussiere auf Trends und Veränderungen, nicht auf statische Zahlen
- Sei direkt und konkret, keine allgemeinen Spartipps
- Antworte in 100-150 Wörtern
PROMPT;

    /**
     * Get structured AI insights (JSON format for the dedicated page).
     */
    public function getStructuredInsights(): ?array
    {
        $provider = $this->resolveProvider();
        if (! $provider) {
            return null;
        }

        $snapshot = FinancialSnapshot::capture();

        if (empty($snapshot->monthlyRatios)) {
            return null;
        }

        $cacheKey = 'ai_structured_insights_'.$snapshot->hash;
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = $provider->chat(
                self::SYSTEM_PROMPT,
                $snapshot->toPromptContext(),
                maxTokens: 3000,
            );

            $parsed = $this->parseJsonResponse($response);

            $result = [
                'structured' => $parsed,
                'raw' => $parsed ? null : $response,
                'provider' => $provider->getName(),
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

            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;
        } catch (\Throwable $e) {
            return [
                'structured' => null,
                'raw' => null,
                'error' => 'KI-Analyse fehlgeschlagen: '.$e->getMessage(),
                'provider' => $provider->getName(),
            ];
        }
    }

    /**
     * Force refresh structured insights (bypass cache).
     */
    public function refreshStructuredInsights(): ?array
    {
        $snapshot = FinancialSnapshot::capture();
        Cache::forget('ai_structured_insights_'.$snapshot->hash);

        return $this->getStructuredInsights();
    }

    /**
     * Get legacy text-based insights (for dashboard card).
     */
    public function getInsights(): ?array
    {
        $provider = $this->resolveProvider();
        if (! $provider) {
            return null;
        }

        $snapshot = FinancialSnapshot::capture();

        if (empty($snapshot->monthlyRatios)) {
            return null;
        }

        // Check cache
        $cacheKey = 'ai_insights_'.$snapshot->hash;
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = $provider->chat(
                self::LEGACY_SYSTEM_PROMPT,
                $snapshot->toPromptContext()
            );

            $result = [
                'insights' => $response,
                'provider' => $provider->getName(),
                'generatedAt' => now()->toIso8601String(),
                'snapshotHash' => $snapshot->hash,
            ];

            // Cache for 24 hours per snapshot hash
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;
        } catch (\Throwable $e) {
            return [
                'insights' => null,
                'error' => 'KI-Analyse fehlgeschlagen: '.$e->getMessage(),
                'provider' => $provider->getName(),
            ];
        }
    }

    /**
     * Force refresh insights (bypass cache).
     */
    public function refreshInsights(): ?array
    {
        $snapshot = FinancialSnapshot::capture();
        Cache::forget('ai_insights_'.$snapshot->hash);

        return $this->getInsights();
    }

    private function parseJsonResponse(string $response): ?array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $response);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if (! is_array($decoded)) {
            return null;
        }

        // Validate required fields exist
        $required = ['healthScore', 'healthTrend', 'summary', 'highlights', 'recommendations'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $decoded)) {
                return null;
            }
        }

        // Clamp health score
        $decoded['healthScore'] = max(0, min(100, (int) $decoded['healthScore']));

        // Ensure arrays
        $decoded['highlights'] = is_array($decoded['highlights']) ? $decoded['highlights'] : [];
        $decoded['categoryInsights'] = is_array($decoded['categoryInsights'] ?? null) ? $decoded['categoryInsights'] : [];
        $decoded['recommendations'] = is_array($decoded['recommendations']) ? $decoded['recommendations'] : [];
        $decoded['loanInsights'] = is_array($decoded['loanInsights'] ?? null) ? $decoded['loanInsights'] : [];

        return $decoded;
    }

    private function resolveProvider(): ?AiProviderInterface
    {
        $providerType = Setting::get('ai_provider', 'none');

        $apiKey = null;
        $rawKey = Setting::get('ai_api_key');
        if ($rawKey) {
            try {
                $apiKey = decrypt($rawKey);
            } catch (\Throwable) {
                $apiKey = $rawKey; // Fallback for unencrypted legacy keys
            }
        }

        $model = Setting::get('ai_model', '');
        $baseUrl = Setting::get('ai_base_url', '');

        return match ($providerType) {
            'claude' => new ClaudeProvider(
                apiKey: $apiKey ?? '',
                model: $model ?: 'claude-sonnet-4-5-20250514',
            ),
            'openai' => new OpenAiCompatibleProvider(
                baseUrl: $baseUrl ?: 'https://api.openai.com',
                model: $model ?: 'gpt-4o',
                apiKey: $apiKey,
            ),
            'ollama' => new OpenAiCompatibleProvider(
                baseUrl: $baseUrl ?: 'http://localhost:11434',
                model: $model ?: 'llama3',
                apiKey: null,
            ),
            default => null,
        };
    }
}
