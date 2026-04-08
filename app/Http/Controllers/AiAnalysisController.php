<?php

namespace App\Http\Controllers;

use App\Ai\Agents\FinancialAnalyst;
use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AiAnalysisController extends Controller
{
    /**
     * Show the dedicated AI analysis page.
     * Anomalies and history are passed as Inertia props (instant, no AI call).
     */
    public function index()
    {
        $snapshot = FinancialSnapshot::capture();

        return Inertia::render('AI/Index', [
            'aiEnabled' => AiConfigService::isEnabled(),
            'anomalies' => $snapshot->anomalies,
            'budgetUtilization' => $snapshot->budgetUtilization,
            'currentMonthComplete' => $snapshot->currentMonthComplete,
            'topGrowingCategories' => $snapshot->topGrowingCategories,
            'topShrinkingCategories' => $snapshot->topShrinkingCategories,
            'monthlyRatios' => $snapshot->monthlyRatios,
            'history' => FinancialAnalyst::history(),
        ]);
    }

    /**
     * Fetch cached AI insights (no AI call — cache-only).
     */
    public function insights()
    {
        if (! AiConfigService::isEnabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert. Gehe zu Einstellungen → KI-Konfiguration.',
            ]);
        }

        $cached = Cache::get('ai_structured_insights');
        if ($cached) {
            return response()->json(['enabled' => true, ...$cached]);
        }

        return response()->json(['enabled' => true, 'structured' => null]);
    }

    /**
     * Trigger a new AI analysis (the only path that calls the AI).
     */
    public function refresh()
    {
        if (! AiConfigService::isEnabled()) {
            return response()->json([
                'enabled' => false,
                'message' => 'KI nicht konfiguriert.',
            ]);
        }

        Cache::forget('ai_structured_insights');

        try {
            $result = FinancialAnalyst::analyze();

            if (! $result) {
                return response()->json([
                    'enabled' => true,
                    'structured' => null,
                    'error' => 'Nicht genügend Daten für eine Analyse.',
                ]);
            }

            Cache::put('ai_structured_insights', $result, now()->addHours(24));

            return response()->json(['enabled' => true, ...$result]);
        } catch (\Throwable $e) {
            return response()->json([
                'enabled' => true,
                'structured' => null,
                'error' => 'KI-Analyse fehlgeschlagen: '.$e->getMessage(),
                'provider' => AiConfigService::providerDisplayName(),
            ]);
        }
    }
}
