<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Send a prompt and get a response.
     *
     * @return string The AI response text
     */
    public function chat(string $systemPrompt, string $userMessage, int $maxTokens = 1024): string;

    /**
     * Get the provider name for display.
     */
    public function getName(): string;
}
