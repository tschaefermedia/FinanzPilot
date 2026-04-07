<?php

namespace App\Ai\Agents;

use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

#[MaxTokens(1024)]
#[Temperature(0.5)]
#[Timeout(45)]
class FinancialChat implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        $snapshot = FinancialSnapshot::capture();

        $base = <<<'INSTRUCTIONS'
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
INSTRUCTIONS;

        return $base."\n\n--- FINANZDATEN ---\n".$snapshot->toPromptContext();
    }

    /**
     * Send a chat message within a conversation.
     *
     * @return array{message: string, conversationId: string, provider: string}
     */
    public static function chat(string $message, ?string $conversationId = null, ?int $userId = null): array
    {
        if (! AiConfigService::configure()) {
            throw new \RuntimeException('KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.');
        }

        $model = AiConfigService::model();
        $agent = new self;

        // Use a dummy user ID for single-user app
        $agent = $agent->forUser((object) ['id' => $userId ?? 1]);

        if ($conversationId) {
            $agent = $agent->continue($conversationId, as: (object) ['id' => $userId ?? 1]);
        }

        $response = $model
            ? $agent->model($model)->prompt($message)
            : $agent->prompt($message);

        return [
            'message' => $response->text,
            'conversationId' => $response->conversationId,
            'provider' => AiConfigService::providerDisplayName(),
        ];
    }
}
