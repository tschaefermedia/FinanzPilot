<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExportController extends Controller
{
    public function __construct(
        private ExcelExportService $exportService,
    ) {}

    /**
     * Show export page with month selector.
     */
    public function index()
    {
        // Get available months that have transactions
        $months = Transaction::selectRaw("strftime('%Y-%m', date) as month")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderByDesc('month')
            ->pluck('month')
            ->toArray();

        return Inertia::render('Export/Index', [
            'availableMonths' => $months,
        ]);
    }

    /**
     * Export a single month.
     */
    public function exportMonth(Request $request)
    {
        $request->validate([
            'month' => 'required|string|date_format:Y-m',
        ]);

        $dateFrom = $request->month . '-01';
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $m = (int) date('n', strtotime($dateFrom));
        $y = date('Y', strtotime($dateFrom));
        $title = $months[$m] . ' ' . $y;

        $path = $this->exportService->exportRange($dateFrom, $dateTo, $title);

        return response()->download($path, "FinanzPilot-{$request->month}.xlsx")->deleteFileAfterSend(true);
    }

    /**
     * Export a custom date range.
     */
    public function exportRange(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $path = $this->exportService->exportRange($request->date_from, $request->date_to);

        $filename = "FinanzPilot-{$request->date_from}-bis-{$request->date_to}.xlsx";

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export multiple months as separate sheets in one file.
     */
    public function exportBatch(Request $request)
    {
        $request->validate([
            'months' => 'required|array|min:1',
            'months.*' => 'string|date_format:Y-m',
        ]);

        $path = $this->exportService->exportBatch($request->months);

        $from = min($request->months);
        $to = max($request->months);
        $filename = "FinanzPilot-Batch-{$from}-bis-{$to}.xlsx";

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
