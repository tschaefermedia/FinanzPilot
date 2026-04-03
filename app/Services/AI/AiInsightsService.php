<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class AiInsightsService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Du bist ein erfahrener persönlicher Finanzberater. Analysiere die anonymisierten Finanzdaten und gib konkrete, umsetzbare Empfehlungen auf Deutsch.

Regeln:
- Alle Daten sind relativ (Einkommen = 100 Einheiten), keine absoluten Beträge nennen
- Maximal 3-4 konkrete Empfehlungen
- Jede Empfehlung sollte eine spezifische Handlung vorschlagen
- Fokussiere auf Trends und Veränderungen, nicht auf statische Zahlen
- Sei direkt und konkret, keine allgemeinen Spartipps
- Antworte in 150-200 Wörtern
- Verwende Aufzählungszeichen für Übersichtlichkeit
PROMPT;

    public function getInsights(): ?array
    {
        $provider = $this->resolveProvider();
        if (!$provider) {
            return null;
        }

        $snapshot = FinancialSnapshot::capture();

        if (empty($snapshot->monthlyRatios)) {
            return null;
        }

        // Check cache
        $cacheKey = 'ai_insights_' . $snapshot->hash;
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = $provider->chat(
                self::SYSTEM_PROMPT,
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
                'error' => 'KI-Analyse fehlgeschlagen: ' . $e->getMessage(),
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
        Cache::forget('ai_insights_' . $snapshot->hash);
        return $this->getInsights();
    }

    private function resolveProvider(): ?AiProviderInterface
    {
        $providerType = \App\Models\Setting::get('ai_provider', 'none');

        return match ($providerType) {
            'claude' => new ClaudeProvider(
                apiKey: \App\Models\Setting::get('ai_api_key', ''),
                model: \App\Models\Setting::get('ai_model', 'claude-sonnet-4-5-20250514'),
            ),
            'openai' => new OpenAiCompatibleProvider(
                baseUrl: \App\Models\Setting::get('ai_base_url', 'https://api.openai.com'),
                model: \App\Models\Setting::get('ai_model', 'gpt-4o'),
                apiKey: \App\Models\Setting::get('ai_api_key'),
            ),
            'ollama' => new OpenAiCompatibleProvider(
                baseUrl: \App\Models\Setting::get('ai_base_url', 'http://localhost:11434'),
                model: \App\Models\Setting::get('ai_model', 'llama3'),
                apiKey: null,
            ),
            default => null,
        };
    }
}
