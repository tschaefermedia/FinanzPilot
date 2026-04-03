<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Send a prompt and get a response.
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @return string The AI response text
     */
    public function chat(string $systemPrompt, string $userMessage): string;

    /**
     * Get the provider name for display.
     */
    public function getName(): string;
}
