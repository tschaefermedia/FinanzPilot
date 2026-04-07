<?php

namespace App\Services\AI;

use App\Models\Setting;

class AiConfigService
{
    /**
     * Apply AI provider configuration from the Settings table to Laravel's config.
     * Must be called before using any laravel/ai agent.
     *
     * @return string|null The resolved provider name, or null if AI is disabled.
     */
    public static function configure(): ?string
    {
        $providerType = Setting::get('ai_provider', 'none');

        if ($providerType === 'none') {
            return null;
        }

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

        // Map our setting names to laravel/ai provider names
        $providerName = match ($providerType) {
            'claude' => 'anthropic',
            'openai' => 'openai',
            'ollama' => 'ollama',
            default => null,
        };

        if (! $providerName) {
            return null;
        }

        // Set the default provider
        config(['ai.default' => $providerName]);

        // Configure the provider credentials
        if ($apiKey) {
            config(["ai.providers.{$providerName}.key" => $apiKey]);
        }

        if ($baseUrl) {
            config(["ai.providers.{$providerName}.url" => $baseUrl]);
        }

        return $providerName;
    }

    /**
     * Get the default model for the configured provider.
     */
    public static function model(): ?string
    {
        $model = Setting::get('ai_model', '');
        if ($model) {
            return $model;
        }

        $providerType = Setting::get('ai_provider', 'none');

        return match ($providerType) {
            'claude' => 'claude-sonnet-4-5-20250514',
            'openai' => 'gpt-4o',
            'ollama' => 'llama3',
            default => null,
        };
    }

    /**
     * Check if AI is enabled.
     */
    public static function isEnabled(): bool
    {
        return Setting::get('ai_provider', 'none') !== 'none';
    }

    /**
     * Get a display name for the current provider.
     */
    public static function providerDisplayName(): string
    {
        $providerType = Setting::get('ai_provider', 'none');

        return match ($providerType) {
            'claude' => 'Claude',
            'openai' => 'OpenAI',
            'ollama' => 'Ollama',
            default => 'Nicht konfiguriert',
        };
    }
}
