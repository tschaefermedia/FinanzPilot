<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AiProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'claude-sonnet-4-5-20250514',
    ) {}

    public function chat(string $systemPrompt, string $userMessage, int $maxTokens = 1024): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(45)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: '.$response->body());
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    public function getName(): string
    {
        return 'Claude';
    }
}
