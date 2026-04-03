<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'ai_provider' => config('services.ai.provider', 'none'),
                'ai_model' => config('services.ai.model', ''),
                'ai_base_url' => config('services.ai.base_url', ''),
                'ai_api_key_set' => !empty(config('services.ai.api_key')),
            ],
        ]);
    }

    /**
     * Update AI settings by writing to .env file.
     */
    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'ai_provider' => 'required|in:none,claude,openai,ollama',
            'ai_api_key' => 'nullable|string',
            'ai_model' => 'nullable|string|max:255',
            'ai_base_url' => 'nullable|url|max:255',
        ]);

        $this->setEnv('AI_PROVIDER', $validated['ai_provider']);

        if ($validated['ai_api_key'] !== null) {
            $this->setEnv('AI_API_KEY', $validated['ai_api_key']);
        }

        if ($validated['ai_model'] !== null) {
            $this->setEnv('AI_MODEL', $validated['ai_model']);
        }

        if ($validated['ai_base_url'] !== null) {
            $this->setEnv('AI_BASE_URL', $validated['ai_base_url']);
        }

        // Clear config cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return redirect()->back()->with('success', 'KI-Einstellungen gespeichert. Bitte App neu starten für Änderungen.');
    }

    /**
     * Write a key=value pair to the .env file.
     */
    private function setEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        // Strip newlines from value to prevent injection
        $value = str_replace(["\n", "\r"], '', $value);

        // Always quote the value
        $escapedValue = '"' . addcslashes($value, '"\\') . '"';

        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, $key . '=' . $escapedValue, $envContent);
        } else {
            $envContent .= "\n" . $key . '=' . $escapedValue;
        }

        file_put_contents($envPath, $envContent);
    }
}
