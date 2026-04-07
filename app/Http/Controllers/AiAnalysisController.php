<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AI\AiInsightsService;
use Inertia\Inertia;

class AiAnalysisController extends Controller
{
    public function __construct(
        private AiInsightsService $insightsService,
    ) {}

    /**
     * Show the dedicated AI analysis page.
     */
    public function index()
    {
        $aiEnabled = Setting::get('ai_provider', 'none') !== 'none';

        return Inertia::render('AI/Index', [
            'aiEnabled' => $aiEnabled,
        ]);
    }

    /**
     * Fetch structured AI insights (called via fetch from the page).
     */
    public function insights()
    {
        $insights = $this->insightsService->getStructuredInsights();

        if (! $insights) {
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
     * Refresh structured AI insights (bypass cache).
     */
    public function refresh()
    {
        $insights = $this->insightsService->refreshStructuredInsights();

        if (! $insights) {
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
