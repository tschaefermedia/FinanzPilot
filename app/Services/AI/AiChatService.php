<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AiChatService
{
    private const CHAT_SYSTEM_PROMPT = <<<'PROMPT'
Du bist der Finanzassistent von FinanzPilot. Der Nutzer stellt dir Fragen zu seinen Finanzen. Du hast Zugriff auf seine anonymisierten Finanzdaten (siehe Kontext unten).

FinanzPilot bietet diese Funktionen:
- Übersicht (Dashboard mit Monatsvergleich und Diagrammen)
- Konten (Kontoverwaltung und Salden)
- Buchungen (Transaktionsliste mit Filterung)
- Kategorien (hierarchische Kategorien mit optionalen Monatsbudgets)
- Import (CSV-Import von Bankauszügen)
- Darlehen (Kreditverwaltung mit Tilgungsplan und Zahlungszuordnung)
- Daueraufträge (wiederkehrende Buchungen)
- Export (Excel-Export für Steuerberater)
- KI-Analyse (Finanzübersicht, Trends, Empfehlungen)

Regeln:
- Nenne nur Prozentangaben — niemals absolute Beträge oder Euro-Werte
- Beziehe dich ausschließlich auf die oben gelisteten Funktionen — erfinde keine neuen
- Verwende keine echten Namen aus den Daten — die Daten sind anonymisiert (z.B. "Kredit A")
- Empfehle keine externen Tools oder Apps
- Sei direkt, konkret und hilfreich
- Antworte in klarem Deutsch, formatiere mit Markdown wenn hilfreich
- Halte Antworten auf 100-200 Wörter, es sei denn der Nutzer fragt nach mehr Detail
- Wenn der Nutzer nach etwas fragt, das nicht in den Finanzdaten enthalten ist, sage das ehrlich
PROMPT;

    /**
     * Send a chat message and get a response, maintaining conversation history.
     *
     * @return array{message: string, provider: string}
     */
    public function sendMessage(string $sessionId, string $userMessage): array
    {
        $provider = $this->resolveProvider();
        if (! $provider) {
            throw new \RuntimeException('KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.');
        }

        $snapshot = FinancialSnapshot::capture();
        $history = $this->getHistory($sessionId);

        // Add user message to history
        $history[] = ['role' => 'user', 'content' => $userMessage];

        // Build system prompt with financial context
        $systemPrompt = self::CHAT_SYSTEM_PROMPT."\n\n--- FINANZDATEN ---\n".$snapshot->toPromptContext();

        // Keep conversation manageable — last 20 messages max
        $conversationMessages = array_slice($history, -20);

        $response = $provider->chatWithHistory(
            $systemPrompt,
            $conversationMessages,
            maxTokens: 1024,
        );

        // Add assistant response to history
        $history[] = ['role' => 'assistant', 'content' => $response];

        // Save updated history (1 hour TTL)
        $this->saveHistory($sessionId, $history);

        return [
            'message' => $response,
            'provider' => $provider->getName(),
        ];
    }

    /**
     * Clear conversation history for a session.
     */
    public function clearHistory(string $sessionId): void
    {
        Cache::forget($this->cacheKey($sessionId));
    }

    /**
     * Get conversation history for a session.
     *
     * @return array<array{role: string, content: string}>
     */
    public function getHistory(string $sessionId): array
    {
        return Cache::get($this->cacheKey($sessionId), []);
    }

    private function saveHistory(string $sessionId, array $history): void
    {
        Cache::put($this->cacheKey($sessionId), $history, now()->addHour());
    }

    private function cacheKey(string $sessionId): string
    {
        return 'ai_chat_'.$sessionId;
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
                $apiKey = $rawKey;
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
