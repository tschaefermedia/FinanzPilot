<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Send a single prompt and get a response.
     *
     * @return string The AI response text
     */
    public function chat(string $systemPrompt, string $userMessage, int $maxTokens = 1024): string;

    /**
     * Send a multi-turn conversation and get a response.
     *
     * @param  array<array{role: string, content: string}>  $messages
     * @return string The AI response text
     */
    public function chatWithHistory(string $systemPrompt, array $messages, int $maxTokens = 1024): string;

    /**
     * Get the provider name for display.
     */
    public function getName(): string;
}
