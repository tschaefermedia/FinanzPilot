<?php

namespace App\Services;

use App\Models\Transaction;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportService
{
    private const HEADER_COLOR = '2563EB';
    private const HEADER_FONT_COLOR = 'FFFFFF';
    private const INCOME_COLOR = '22C55E';
    private const EXPENSE_COLOR = 'EF4444';

    /**
     * Export a single month or date range as .xlsx
     */
    public function exportRange(string $dateFrom, string $dateTo, ?string $title = null): string
    {
        $transactions = Transaction::with('category')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('FinanzPilot')
            ->setTitle($title ?? "Export {$dateFrom} bis {$dateTo}");

        // Transaction detail sheet
        $this->buildTransactionSheet($spreadsheet->getActiveSheet(), $transactions, $title ?? "Buchungen");

        // Summary sheet
        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheet($summarySheet, $transactions, $dateFrom, $dateTo);

        $spreadsheet->setActiveSheetIndex(0);

        // Write to temp file
        $filename = 'finanzpilot-export-' . date('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    /**
     * Export multiple months as separate sheets in one file.
     */
    public function exportBatch(array $months): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('FinanzPilot')
            ->setTitle('FinanzPilot Batch-Export');

        $first = true;
        foreach ($months as $month) {
            $dateFrom = $month . '-01';
            $dateTo = date('Y-m-t', strtotime($dateFrom));

            $transactions = Transaction::with('category')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            $monthLabel = $this->germanMonth($dateFrom);

            if ($first) {
                $sheet = $spreadsheet->getActiveSheet();
                $first = false;
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $this->buildTransactionSheet($sheet, $transactions, $monthLabel);
        }

        // Add a combined summary sheet
        $allMonthsFrom = min($months) . '-01';
        $allMonthsTo = date('Y-m-t', strtotime(max($months) . '-01'));
        $allTransactions = Transaction::with('category')
            ->whereBetween('date', [$allMonthsFrom, $allMonthsTo])
            ->orderBy('date')
            ->get();

        $summarySheet = $spreadsheet->createSheet();
        $this->buildSummarySheet($summarySheet, $allTransactions, $allMonthsFrom, $allMonthsTo);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'finanzpilot-batch-' . date('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    private function buildTransactionSheet($sheet, $transactions, string $title): void
    {
        $sheet->setTitle(mb_substr($title, 0, 31)); // Excel sheet name max 31 chars

        // Headers
        $headers = ['Datum', 'Betrag', 'Beschreibung', 'Empfänger', 'Kategorie', 'Quelle', 'Notizen'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        foreach ($headers as $i => $header) {
            $cell = $columns[$i] . '1';
            $sheet->setCellValue($cell, $header);
        }

        // Style headers
        $headerRange = 'A1:G1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::HEADER_FONT_COLOR],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_COLOR],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        if ($transactions->isEmpty()) {
            $sheet->setCellValue('A2', 'Keine Buchungen in diesem Zeitraum.');
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }

        // Data rows
        $row = 2;
        $sourceLabels = [
            'manual' => 'Manuell',
            'sparkasse' => 'Sparkasse',
            'paypal' => 'PayPal',
            'recurring' => 'Dauerauftrag',
        ];

        foreach ($transactions as $tx) {
            $sheet->setCellValue("A{$row}", $tx->date->format('d.m.Y'));
            $sheet->setCellValue("B{$row}", (float) $tx->amount);
            $sheet->setCellValue("C{$row}", $tx->description);
            $sheet->setCellValue("D{$row}", $tx->counterparty ?? '');
            $sheet->setCellValue("E{$row}", $tx->category?->name ?? '');
            $sheet->setCellValue("F{$row}", $sourceLabels[$tx->source] ?? $tx->source);
            $sheet->setCellValue("G{$row}", $tx->notes ?? '');

            // Color amount: green for income, red for expense
            $amountColor = (float) $tx->amount >= 0 ? self::INCOME_COLOR : self::EXPENSE_COLOR;
            $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB($amountColor);

            $row++;
        }

        // Format amount column as German currency
        $lastRow = $row - 1;
        $sheet->getStyle("B2:B{$lastRow}")->getNumberFormat()
            ->setFormatCode('#.##0,00 €;[Red]-#.##0,00 €');

        // Date column format
        $sheet->getStyle("A2:A{$lastRow}")->getNumberFormat()
            ->setFormatCode('DD.MM.YYYY');

        // Auto-size columns
        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Borders
        $sheet->getStyle("A1:G{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Freeze header row
        $sheet->freezePane('A2');
    }

    private function buildSummarySheet($sheet, $transactions, string $dateFrom, string $dateTo): void
    {
        $sheet->setTitle('Zusammenfassung');

        // Title
        $sheet->setCellValue('A1', 'Zusammenfassung');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "Zeitraum: " . date('d.m.Y', strtotime($dateFrom)) . ' bis ' . date('d.m.Y', strtotime($dateTo)));
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Overall totals
        $income = $transactions->where('amount', '>', 0)->sum('amount');
        $expenses = $transactions->where('amount', '<', 0)->sum('amount');
        $balance = $income + $expenses;

        $row = 4;
        $summaryData = [
            ['Einnahmen', $income],
            ['Ausgaben', abs($expenses)],
            ['Differenz', $balance],
            ['Sparquote', $income > 0 ? round(($balance / $income) * 100, 1) . ' %' : '0 %'],
            ['Anzahl Buchungen', $transactions->count()],
        ];

        foreach ($summaryData as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if (is_numeric($value)) {
                $sheet->setCellValue("B{$row}", (float) $value);
                $sheet->getStyle("B{$row}")->getNumberFormat()
                    ->setFormatCode('#.##0,00 €;[Red]-#.##0,00 €');
            } else {
                $sheet->setCellValue("B{$row}", $value);
            }
            $row++;
        }

        // Category breakdown
        $row += 2;
        $sheet->setCellValue("A{$row}", 'Ausgaben nach Kategorie');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue("A{$row}", 'Kategorie');
        $sheet->setCellValue("B{$row}", 'Betrag');
        $sheet->setCellValue("C{$row}", 'Anteil');
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_FONT_COLOR]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_COLOR]],
        ]);
        $row++;

        $categoryTotals = $transactions->where('amount', '<', 0)
            ->groupBy(fn ($tx) => $tx->category?->name ?? 'Ohne Kategorie')
            ->map(fn ($group) => abs($group->sum('amount')))
            ->sortDesc();

        $totalExpenses = abs($expenses);
        foreach ($categoryTotals as $catName => $total) {
            $sheet->setCellValue("A{$row}", $catName);
            $sheet->setCellValue("B{$row}", $total);
            $sheet->getStyle("B{$row}")->getNumberFormat()
                ->setFormatCode('#.##0,00 €');
            $percent = $totalExpenses > 0 ? round(($total / $totalExpenses) * 100, 1) : 0;
            $sheet->setCellValue("C{$row}", $percent . ' %');
            $row++;
        }

        // Auto-size
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
    }

    private function germanMonth(string $date): string
    {
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $m = (int) date('n', strtotime($date));
        $y = date('Y', strtotime($date));
        return ($months[$m] ?? '') . ' ' . $y;
    }
}
