<?php

namespace App\Http\Controllers;

use App\Ai\Agents\FinancialAnalyst;
use App\Services\AI\AiConfigService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AiAnalysisController extends Controller
{
    /**
     * Show the dedicated AI analysis page.
     */
    public function index()
    {
        return Inertia::render('AI/Index', [
            'aiEnabled' => AiConfigService::isEnabled(),
        ]);
    }

    /**
     * Fetch structured AI insights (called via fetch from the page).
     */
    public function insights()
    {
        if (! AiConfigService::isEnabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.',
            ]);
        }

        // Check cache first
        $cacheKey = 'ai_structured_insights';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(['enabled' => true, ...$cached]);
        }

        try {
            $result = FinancialAnalyst::analyze();

            if (! $result) {
                return response()->json([
                    'enabled' => false,
                    'message' => 'Nicht genügend Daten für eine Analyse.',
                ]);
            }

            Cache::put($cacheKey, $result, now()->addHours(24));

            return response()->json(['enabled' => true, ...$result]);
        } catch (\Throwable $e) {
            return response()->json([
                'enabled' => true,
                'structured' => null,
                'raw' => null,
                'error' => 'KI-Analyse fehlgeschlagen: '.$e->getMessage(),
                'provider' => AiConfigService::providerDisplayName(),
            ]);
        }
    }

    /**
     * Refresh structured AI insights (bypass cache).
     */
    public function refresh()
    {
        Cache::forget('ai_structured_insights');

        return $this->insights();
    }
}
