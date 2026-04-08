<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetBudgetStatus;
use App\Ai\Tools\GetCategoryBreakdown;
use App\Ai\Tools\GetLoanDetails;
use App\Ai\Tools\SearchTransactions;
use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[MaxTokens(1024)]
#[MaxSteps(5)]
#[Temperature(0.5)]
#[Timeout(120)]
class FinancialChat implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        $snapshot = FinancialSnapshot::capture();
        $stabilityLabel = $snapshot->incomeStability < 10 ? 'sehr stabil' : ($snapshot->incomeStability < 25 ? 'mäßig stabil' : 'schwankend');
        $monthCount = count($snapshot->monthlyRatios);

        return <<<INSTRUCTIONS
Du bist der Finanzassistent von FinanzPilot. Der Nutzer stellt dir Fragen zu seinen Finanzen.

Du hast Werkzeuge um Finanzdaten abzurufen — nutze sie um präzise Antworten zu geben.

Kurzübersicht:
- Sparquote: {$snapshot->savingsRate}%
- Einkommensstabilität: {$stabilityLabel}
- Buchungen: {$snapshot->transactionCount} im aktuellen Monat
- Datenspanne: {$monthCount} Monate

Regeln:
- Nenne nur Prozentangaben — niemals absolute Beträge oder Euro-Werte
- Verwende keine echten Namen — die Daten sind anonymisiert
- Empfehle keine externen Tools oder Apps
- Sei direkt, konkret und hilfreich
- Antworte in klarem Deutsch, formatiere mit **fett** für Betonung
- Halte Antworten auf 100-200 Wörter
- Nutze die Werkzeuge um Daten nachzuschlagen statt zu raten
INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new SearchTransactions,
            new GetBudgetStatus,
            new GetCategoryBreakdown,
            new GetLoanDetails,
        ];
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

        $agent = new self;

        // Use a dummy user ID for single-user app
        $agent = $agent->forUser((object) ['id' => $userId ?? 1]);

        if ($conversationId) {
            $agent = $agent->continue($conversationId, as: (object) ['id' => $userId ?? 1]);
        }

        $response = $agent->prompt(
            $message,
            model: AiConfigService::model(),
        );

        return [
            'message' => $response->text,
            'conversationId' => $response->conversationId,
            'provider' => AiConfigService::providerDisplayName(),
        ];
    }
}
