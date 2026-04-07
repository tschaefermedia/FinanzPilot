<?php

namespace App\Http\Controllers;

use App\Ai\Agents\DashboardInsights;
use App\Services\AI\AiConfigService;
use Illuminate\Support\Facades\Cache;

class AiInsightsController extends Controller
{
    /**
     * Get AI insights (called via fetch from the dashboard).
     */
    public function index()
    {
        if (! AiConfigService::isEnabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.',
            ]);
        }

        $cacheKey = 'ai_dashboard_insights';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(['enabled' => true, ...$cached]);
        }

        try {
            $result = DashboardInsights::analyze();

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
                'insights' => null,
                'error' => 'KI-Analyse fehlgeschlagen: '.$e->getMessage(),
                'provider' => AiConfigService::providerDisplayName(),
            ]);
        }
    }

    /**
     * Refresh AI insights (bypass cache).
     */
    public function refresh()
    {
        Cache::forget('ai_dashboard_insights');

        return $this->index();
    }
}
