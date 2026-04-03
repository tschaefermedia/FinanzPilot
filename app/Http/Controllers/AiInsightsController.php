<?php

namespace App\Http\Controllers;

use App\Services\AI\AiInsightsService;

class AiInsightsController extends Controller
{
    public function __construct(
        private AiInsightsService $insightsService,
    ) {}

    /**
     * Get AI insights (called via fetch from the dashboard).
     */
    public function index()
    {
        $insights = $this->insightsService->getInsights();

        if (!$insights) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.',
            ]);
        }

        return response()->json([
            'enabled' => true,
            ...$insights,
        ]);
    }

    /**
     * Refresh AI insights (bypass cache).
     */
    public function refresh()
    {
        $insights = $this->insightsService->refreshInsights();

        if (!$insights) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert.',
            ]);
        }

        return response()->json([
            'enabled' => true,
            ...$insights,
        ]);
    }
}
