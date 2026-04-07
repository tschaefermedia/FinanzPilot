<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class OpenAiCompatibleProvider implements AiProviderInterface
{
    public function __construct(
        private string $baseUrl,
        private string $model,
        private ?string $apiKey = null,
    ) {}

    public function chat(string $systemPrompt, string $userMessage, int $maxTokens = 1024): string
    {
        $headers = ['content-type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        $url = rtrim($this->baseUrl, '/').'/v1/chat/completions';

        $response = Http::withHeaders($headers)->timeout(60)->post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'max_tokens' => $maxTokens,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('AI API error: '.$response->body());
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function getName(): string
    {
        return 'OpenAI-Compatible ('.$this->model.')';
    }
}
