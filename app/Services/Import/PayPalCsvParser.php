<?php

namespace App\Services\Import;

class PayPalCsvParser implements ParserInterface
{
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // Handle BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Ensure UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        if (count($lines) < 2) {
            return [];
        }

        // Parse header — PayPal uses comma delimiter with quoted fields
        $headers = str_getcsv(array_shift($lines), ',', '"');
        $headers = array_map('trim', $headers);

        // Find column indices by known German PayPal header names
        $dateCol = $this->findColumn($headers, ['Datum', 'Date']);
        $nameCol = $this->findColumn($headers, ['Name', 'Empfänger']);
        $grossCol = $this->findColumn($headers, ['Brutto', 'Gross']);
        $typeCol = $this->findColumn($headers, ['Typ', 'Type']);
        $descCol = $this->findColumn($headers, ['Betreff', 'Artikelbezeichnung', 'Subject', 'Item Title']);
        $refCol = $this->findColumn($headers, ['Transaktionscode', 'Transaction ID']);
        $currencyCol = $this->findColumn($headers, ['Währung', 'Currency']);

        if ($dateCol === null || $grossCol === null) {
            return [];
        }

        $transactions = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"');

            if (count($row) < count($headers)) {
                continue;
            }

            // Skip non-EUR transactions
            if ($currencyCol !== null) {
                $currency = trim($row[$currencyCol] ?? '');
                if ($currency && $currency !== 'EUR') {
                    continue;
                }
            }

            $date = $this->parseDate(trim($row[$dateCol] ?? ''));
            $amount = $this->parseAmount(trim($row[$grossCol] ?? ''));

            if (!$date || $amount === null) {
                continue;
            }

            $name = $nameCol !== null ? trim($row[$nameCol] ?? '') : null;
            $description = $descCol !== null ? trim($row[$descCol] ?? '') : ($typeCol !== null ? trim($row[$typeCol] ?? '') : '');
            $reference = $refCol !== null ? trim($row[$refCol] ?? '') : null;

            // Use description or type as description, name as counterparty
            if (!$description && $name) {
                $description = 'PayPal: ' . $name;
            } elseif (!$description) {
                $description = 'PayPal-Transaktion';
            }

            if (!$reference) {
                $reference = mb_substr($description, 0, 50);
            }

            $hash = ParsedTransaction::computeHash($date, $amount, $reference);

            $transactions[] = new ParsedTransaction(
                date: $date,
                amount: $amount,
                description: $description,
                counterparty: $name ?: null,
                reference: $reference,
                hash: $hash,
                rawData: array_combine($headers, $row),
            );
        }

        return $transactions;
    }

    public function canHandle(string $filePath): bool
    {
        $content = file_get_contents($filePath, false, null, 0, 4096);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $firstLine = strtok($content, "\n");

        // Check for PayPal-specific headers
        return (str_contains($firstLine, 'Transaktionscode') || str_contains($firstLine, 'Transaction ID'))
            && (str_contains($firstLine, 'Brutto') || str_contains($firstLine, 'Gross'));
    }

    public function getSourceType(): string
    {
        return 'paypal';
    }

    private function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers);
            if ($index !== false) {
                return $index;
            }
        }
        return null;
    }

    private function parseDate(string $value): ?string
    {
        if (!$value) return null;

        // DD.MM.YYYY (German)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    private function parseAmount(string $value): ?float
    {
        if (!$value) return null;

        // Handle both German (1.234,56) and English (1,234.56) formats
        // If it contains comma followed by exactly 2 digits at end, it's German format
        if (preg_match('/,\d{2}$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        $value = preg_replace('/[^\d.\-+]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
