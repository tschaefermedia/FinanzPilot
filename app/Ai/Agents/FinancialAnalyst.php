<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Analysis\CategoryAnalysis;
use App\Ai\Agents\Analysis\HealthScoreAnalysis;
use App\Ai\Agents\Analysis\HighlightsAnalysis;
use App\Ai\Agents\Analysis\LoanAnalysis;
use App\Ai\Agents\Analysis\RecommendationsAnalysis;
use App\Models\AiInsight;
use App\Services\AI\AiConfigService;
use App\Services\AI\FinancialSnapshot;

class FinancialAnalyst
{
    /**
     * Run the analysis using focused sub-agents and return structured results.
     */
    public static function analyze(): ?array
    {
        if (! AiConfigService::configure()) {
            return null;
        }

        $snapshot = FinancialSnapshot::capture();
        if (empty($snapshot->monthlyRatios)) {
            return null;
        }

        $model = AiConfigService::model();

        // Get previous analysis for comparison
        $previous = AiInsight::latest()->first();
        $previousSummary = $previous?->structured_data['summary'] ?? '';

        // 1. Health score first (recommendations reference it)
        $health = HealthScoreAnalysis::run($snapshot, $model, $previousSummary);

        // 2. Remaining focused calls
        $highlights = HighlightsAnalysis::run($snapshot, $model);
        $categories = CategoryAnalysis::run($snapshot, $model);
        $recommendations = RecommendationsAnalysis::run($snapshot, $health, $model);
        $loans = ! empty($snapshot->loanSummary) ? LoanAnalysis::run($snapshot, $model) : [];

        // Merge + normalize
        $structured = self::normalize([
            ...$health,
            'highlights' => $highlights,
            'categoryInsights' => $categories,
            'recommendations' => $recommendations,
            'loanInsights' => $loans,
            'loanNameMap' => $snapshot->loanNameMap,
        ]);

        // Store in history
        AiInsight::create([
            'health_score' => $structured['healthScore'] ?? null,
            'health_trend' => $structured['healthTrend'] ?? null,
            'structured_data' => $structured,
            'snapshot_hash' => $snapshot->hash,
            'provider' => AiConfigService::providerDisplayName(),
        ]);

        return [
            'structured' => $structured,
            'provider' => AiConfigService::providerDisplayName(),
            'generatedAt' => now()->toIso8601String(),
            'snapshotHash' => $snapshot->hash,
            'snapshot' => [
                'monthlyRatios' => $snapshot->monthlyRatios,
                'savingsRate' => $snapshot->savingsRate,
                'savingsRateTrend' => $snapshot->savingsRateTrend,
                'topGrowingCategories' => $snapshot->topGrowingCategories,
                'topShrinkingCategories' => $snapshot->topShrinkingCategories,
                'budgetUtilization' => $snapshot->budgetUtilization,
                'recurringCoveragePercent' => $snapshot->recurringCoveragePercent,
                'incomeStability' => $snapshot->incomeStability,
                'anomalies' => $snapshot->anomalies,
                'categoryTrends' => $snapshot->categoryTrends,
                'currentMonthComplete' => $snapshot->currentMonthComplete,
            ],
        ];
    }

    /**
     * Get past analyses for the history timeline.
     */
    public static function history(int $limit = 10): array
    {
        return AiInsight::latest()
            ->limit($limit)
            ->get()
            ->map(fn (AiInsight $insight) => [
                'id' => $insight->id,
                'healthScore' => $insight->health_score,
                'healthTrend' => $insight->health_trend,
                'summary' => $insight->structured_data['summary'] ?? null,
                'highlights' => $insight->structured_data['highlights'] ?? [],
                'loanNameMap' => $insight->structured_data['loanNameMap'] ?? [],
                'provider' => $insight->provider,
                'createdAt' => $insight->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Normalize AI response to ensure consistent structure regardless of model quality.
     * Some models (e.g. Ollama/Gemma) return plain strings instead of objects for arrays.
     */
    private static function normalize(array $data): array
    {
        // Trim summary
        if (isset($data['summary'])) {
            $data['summary'] = trim($data['summary']);
        }

        // Normalize highlights
        if (isset($data['highlights']) && is_array($data['highlights'])) {
            $data['highlights'] = array_values(array_filter(array_map(function ($h) {
                if (is_string($h)) {
                    $h = ['type' => 'warning', 'title' => self::extractTitle($h), 'detail' => $h];
                } elseif (is_array($h) && ! isset($h['type'])) {
                    $h = ['type' => 'warning', 'title' => $h['title'] ?? '', 'detail' => $h['detail'] ?? ''];
                }
                // If detail is empty but title exists, copy title → detail
                if (is_array($h) && empty(trim($h['detail'] ?? '')) && ! empty(trim($h['title'] ?? ''))) {
                    $h['detail'] = $h['title'];
                }

                return is_array($h) && ! empty(trim($h['detail'] ?? '')) ? $h : null;
            }, $data['highlights'])));
        }

        // Normalize categoryInsights
        if (isset($data['categoryInsights']) && is_array($data['categoryInsights'])) {
            $data['categoryInsights'] = array_values(array_filter(array_map(function ($c) {
                if (is_string($c)) {
                    return ['category' => $c, 'trend' => 'stable', 'comment' => ''];
                }
                if (is_array($c) && ! isset($c['category'])) {
                    return null;
                }

                return $c;
            }, $data['categoryInsights'])));
        }

        // Normalize recommendations
        if (isset($data['recommendations']) && is_array($data['recommendations'])) {
            $data['recommendations'] = array_values(array_filter(array_map(function ($r) {
                if (is_string($r)) {
                    $r = ['priority' => 'medium', 'title' => self::extractTitle($r), 'detail' => $r, 'impact' => ''];
                } elseif (is_array($r) && ! isset($r['priority'])) {
                    $r = ['priority' => 'medium', 'title' => $r['title'] ?? '', 'detail' => $r['detail'] ?? '', 'impact' => $r['impact'] ?? ''];
                }
                // If detail is empty but title exists, copy title → detail
                if (is_array($r) && empty(trim($r['detail'] ?? '')) && ! empty(trim($r['title'] ?? ''))) {
                    $r['detail'] = $r['title'];
                }

                return is_array($r) && ! empty(trim($r['detail'] ?? '')) ? $r : null;
            }, $data['recommendations'])));
        }

        // Normalize loanInsights
        if (isset($data['loanInsights']) && is_array($data['loanInsights'])) {
            $normalized = [];
            foreach ($data['loanInsights'] as $l) {
                if (is_array($l) && isset($l['loan']) && isset($l['comment'])) {
                    // Strip field-name prefixes from comments
                    $l['comment'] = preg_replace('/^(description|beschreibung|kommentar)\s*:\s*/i', '', trim($l['comment']));
                    $normalized[] = $l;
                } elseif (is_array($l)) {
                    foreach ($l as $name => $info) {
                        $comment = is_array($info) ? implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($info), array_values($info))) : (string) $info;
                        $normalized[] = ['loan' => $name, 'comment' => trim($comment)];
                    }
                } elseif (is_string($l)) {
                    $normalized[] = ['loan' => $l, 'comment' => ''];
                }
            }
            $data['loanInsights'] = self::mergeAlternatingLoanInsights($normalized);
        }

        return $data;
    }

    /**
     * Merge alternating key/value loan entries from local models.
     * Input:  [{loan:"Kredit",comment:"Kredit A"},{loan:"Fortschritt",comment:"19.5%"},...]
     * Output: [{loan:"Kredit A",comment:"Fortschritt: 19.5%"},...]
     */
    private static function mergeAlternatingLoanInsights(array $insights): array
    {
        // Detect alternating pattern: entries where comment matches "Kredit [A-Z]"
        $hasNameEntries = false;
        foreach ($insights as $entry) {
            if (preg_match('/^Kredit [A-Z]$/', $entry['comment'] ?? '')) {
                $hasNameEntries = true;
                break;
            }
        }

        if (! $hasNameEntries) {
            return $insights;
        }

        $merged = [];
        $currentLoan = null;
        $currentDetails = [];

        foreach ($insights as $entry) {
            $comment = $entry['comment'] ?? '';
            if (preg_match('/^Kredit [A-Z]$/', $comment)) {
                // Flush previous loan
                if ($currentLoan !== null) {
                    $merged[] = ['loan' => $currentLoan, 'comment' => implode(', ', $currentDetails) ?: ''];
                }
                $currentLoan = $comment;
                $currentDetails = [];
            } elseif ($currentLoan !== null) {
                $key = $entry['loan'] ?? '';
                $val = $comment;
                $currentDetails[] = $key ? "{$key}: {$val}" : $val;
            } else {
                $merged[] = $entry;
            }
        }

        // Flush last loan
        if ($currentLoan !== null) {
            $merged[] = ['loan' => $currentLoan, 'comment' => implode(', ', $currentDetails) ?: ''];
        }

        return $merged;
    }

    /**
     * Extract a short title from a longer text (first sentence or clause).
     */
    private static function extractTitle(string $text): string
    {
        // Try splitting on first period followed by space
        if (preg_match('/^(.{20,80}?)\.\s/', $text, $m)) {
            return $m[1].'.';
        }

        // Try splitting on first comma followed by space (if short enough)
        if (preg_match('/^(.{15,60}?),\s/', $text, $m)) {
            return $m[1];
        }

        // Fallback: truncate at word boundary
        if (mb_strlen($text) > 70) {
            $truncated = mb_substr($text, 0, 70);
            $lastSpace = mb_strrpos($truncated, ' ');

            return $lastSpace ? mb_substr($truncated, 0, $lastSpace).'...' : $truncated.'...';
        }

        return $text;
    }
}
