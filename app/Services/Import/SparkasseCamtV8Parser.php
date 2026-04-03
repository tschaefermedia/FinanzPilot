<?php

namespace App\Services\Import;

class SparkasseCamtV8Parser implements ParserInterface
{
    // Column name mappings (German headers from CSV-CAMT V8)
    private const COLUMN_MAP = [
        'Buchungstag' => 'date',
        'Betrag' => 'amount',
        'Verwendungszweck' => 'description',
        'Beguenstigter/Zahlungspflichtiger' => 'counterparty',
        'Kundenreferenz (End-to-End)' => 'reference',
        'Buchungstext' => 'booking_type',
        'Kontonummer/IBAN' => 'iban',
        'Valutadatum' => 'value_date',
        'Waehrung' => 'currency',
    ];

    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // Detect and convert encoding — V8 should be UTF-8 but handle ISO-8859-1 fallback
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        if (count($lines) < 2) {
            return [];
        }

        // Parse header row
        $headers = str_getcsv(array_shift($lines), ';', '"');
        $headers = array_map('trim', $headers);

        $transactions = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, ';', '"');

            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $mapped = array_combine($headers, $row);

            $date = $this->parseDate($mapped['Buchungstag'] ?? '');
            $amount = $this->parseAmount($mapped['Betrag'] ?? '0');
            $description = trim($mapped['Verwendungszweck'] ?? '');
            $counterparty = trim($mapped['Beguenstigter/Zahlungspflichtiger'] ?? '') ?: null;
            $reference = trim($mapped['Kundenreferenz (End-to-End)'] ?? '') ?: null;

            if (!$date) {
                continue; // Skip rows without valid date
            }

            // If reference is empty or "NOTPROVIDED", use description fragment
            if (!$reference || $reference === 'NOTPROVIDED') {
                $reference = mb_substr($description, 0, 50);
            }

            $hash = ParsedTransaction::computeHash($date, $amount, $reference ?? $description);

            $transactions[] = new ParsedTransaction(
                date: $date,
                amount: $amount,
                description: $description ?: ($mapped['Buchungstext'] ?? 'Keine Beschreibung'),
                counterparty: $counterparty,
                reference: $reference,
                hash: $hash,
                rawData: $mapped,
            );
        }

        return $transactions;
    }

    public function canHandle(string $filePath): bool
    {
        $content = file_get_contents($filePath, false, null, 0, 2048);

        // Detect encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $firstLine = strtok($content, "\n");

        // Check for key Sparkasse CSV-CAMT headers
        return str_contains($firstLine, 'Auftragskonto')
            && str_contains($firstLine, 'Buchungstag')
            && str_contains($firstLine, 'Betrag');
    }

    public function getSourceType(): string
    {
        return 'sparkasse';
    }

    /**
     * Parse German date format (DD.MM.YY or DD.MM.YYYY) to YYYY-MM-DD
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (!$value) return null;

        // DD.MM.YY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $value, $m)) {
            $year = (int) $m[3];
            $year = $year >= 70 ? 1900 + $year : 2000 + $year;
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }

        // DD.MM.YYYY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /**
     * Parse German amount format: "1.234,56" or "-56,78" or "1234,56"
     */
    private function parseAmount(string $value): float
    {
        $value = trim($value);
        if (!$value) return 0.0;

        // Remove thousands separator (period), replace decimal comma with period
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }
}
