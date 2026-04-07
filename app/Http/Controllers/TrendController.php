<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TrendController extends Controller
{
    public function __invoke()
    {
        $twelveMonthsAgo = now()->subMonths(12)->startOfMonth()->toDateString();
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth()->toDateString();
        $currentMonth = now()->format('Y-m');
        $prevMonth = now()->subMonth()->format('Y-m');

        // 12-month income/expense totals
        $monthlyData = Transaction::selectRaw("
                strftime('%Y-%m', date) as month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
            ")
            ->where('date', '>=', $twelveMonthsAgo)
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'))
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderBy('month')
            ->get()
            ->map(fn ($m) => [
                'month' => $m->month,
                'income' => round((float) $m->income, 2),
                'expenses' => round((float) $m->expenses, 2),
            ])->values();

        // Per-category 6-month spending
        $categoryMonthly = Transaction::select(
            'categories.name as category_name',
            DB::raw("strftime('%Y-%m', transactions.date) as month"),
            DB::raw('SUM(ABS(transactions.amount)) as total')
        )
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
            ->where('transactions.date', '>=', $sixMonthsAgo)
            ->groupBy('categories.name', DB::raw("strftime('%Y-%m', transactions.date)"))
            ->get();

        // Build category trends with anomaly detection
        $categoryGroups = $categoryMonthly->groupBy('category_name');
        $categoryTrends = [];

        foreach ($categoryGroups as $categoryName => $months) {
            $monthMap = $months->pluck('total', 'month')->map(fn ($v) => round((float) $v, 2));
            $currentTotal = $monthMap[$currentMonth] ?? 0;
            $prevTotal = $monthMap[$prevMonth] ?? 0;

            // Change percentage vs previous month
            $changePercent = $prevTotal > 0
                ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1)
                : ($currentTotal > 0 ? 100 : 0);

            // 3-month trailing average (months before current)
            $trailingMonths = [];
            for ($i = 1; $i <= 3; $i++) {
                $m = now()->subMonths($i)->format('Y-m');
                if (isset($monthMap[$m])) {
                    $trailingMonths[] = $monthMap[$m];
                }
            }
            $threeMonthAvg = count($trailingMonths) > 0
                ? round(array_sum($trailingMonths) / count($trailingMonths), 2)
                : 0;

            // Anomaly: current month > 130% of 3-month average
            $isAnomaly = $threeMonthAvg > 0 && $currentTotal > ($threeMonthAvg * 1.3);
            $anomalyPercent = $threeMonthAvg > 0
                ? round((($currentTotal - $threeMonthAvg) / $threeMonthAvg) * 100, 1)
                : 0;

            $categoryTrends[] = [
                'category' => $categoryName,
                'currentMonth' => $currentTotal,
                'previousMonth' => $prevTotal,
                'changePercent' => $changePercent,
                'threeMonthAvg' => $threeMonthAvg,
                'isAnomaly' => $isAnomaly,
                'anomalyPercent' => $anomalyPercent,
                'monthlyData' => $monthMap,
            ];
        }

        // Sort: anomalies first, then by current month descending
        usort($categoryTrends, function ($a, $b) {
            if ($a['isAnomaly'] !== $b['isAnomaly']) {
                return $b['isAnomaly'] <=> $a['isAnomaly'];
            }

            return $b['currentMonth'] <=> $a['currentMonth'];
        });

        return Inertia::render('Trends/Index', [
            'monthlyData' => $monthlyData,
            'categoryTrends' => $categoryTrends,
            'currentMonth' => $currentMonth,
        ]);
    }
}
