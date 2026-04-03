<?php

namespace App\Services\Import;

class GenericCsvParser implements ParserInterface
{
    /**
     * Column mapping: ['date' => column_index, 'amount' => column_index, ...]
     * Required keys: date, amount, description
     * Optional keys: counterparty, reference
     */
    public function __construct(
        private array $columnMapping = [],
        private string $delimiter = ',',
        private string $encoding = 'UTF-8',
        private string $dateFormat = 'Y-m-d',
        private bool $hasHeader = true,
        private string $sourceName = 'generic',
    ) {}

    public static function fromMapping(array $mapping): self
    {
        return new self(
            columnMapping: $mapping['columns'] ?? [],
            delimiter: $mapping['delimiter'] ?? ',',
            encoding: $mapping['encoding'] ?? 'UTF-8',
            dateFormat: $mapping['date_format'] ?? 'Y-m-d',
            hasHeader: $mapping['has_header'] ?? true,
            sourceName: $mapping['source_name'] ?? 'generic',
        );
    }

    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // Handle encoding
        if ($this->encoding !== 'UTF-8' && !mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', $this->encoding);
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        if ($this->hasHeader && count($lines) > 0) {
            array_shift($lines); // Remove header row
        }

        $transactions = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, $this->delimiter, '"');

            $date = $this->extractDate($row);
            $amount = $this->extractAmount($row);
            $description = $this->extractField($row, 'description') ?? '';

            if (!$date || $amount === null) {
                continue;
            }

            $counterparty = $this->extractField($row, 'counterparty');
            $reference = $this->extractField($row, 'reference') ?? mb_substr($description, 0, 50);

            $hash = ParsedTransaction::computeHash($date, $amount, $reference);

            $transactions[] = new ParsedTransaction(
                date: $date,
                amount: $amount,
                description: $description,
                counterparty: $counterparty,
                reference: $reference,
                hash: $hash,
                rawData: $row,
            );
        }

        return $transactions;
    }

    public function canHandle(string $filePath): bool
    {
        // Generic parser can handle anything if column mapping is provided
        return !empty($this->columnMapping);
    }

    public function getSourceType(): string
    {
        return $this->sourceName;
    }

    private function extractField(array $row, string $field): ?string
    {
        $index = $this->columnMapping[$field] ?? null;
        if ($index === null || !isset($row[$index])) {
            return null;
        }
        $value = trim($row[$index]);
        return $value !== '' ? $value : null;
    }

    private function extractDate(array $row): ?string
    {
        $raw = $this->extractField($row, 'date');
        if (!$raw) return null;

        // Try common German format first (DD.MM.YYYY)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // DD.MM.YY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $raw, $m)) {
            $year = (int) $m[3];
            $year = $year >= 70 ? 1900 + $year : 2000 + $year;
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }

        // Try PHP date parsing as fallback
        $parsed = date_create_from_format($this->dateFormat, $raw);
        if ($parsed) {
            return $parsed->format('Y-m-d');
        }

        // ISO format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        return null;
    }

    private function extractAmount(array $row): ?float
    {
        $raw = $this->extractField($row, 'amount');
        if ($raw === null) return null;

        // German format: remove thousands dots, replace comma with period
        $value = str_replace('.', '', $raw);
        $value = str_replace(',', '.', $value);

        // Remove currency symbols and spaces
        $value = preg_replace('/[^\d.\-+]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Get the first N rows of a file for preview (used in column mapping UI).
     */
    public static function previewFile(string $filePath, string $delimiter = ',', int $rows = 5): array
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        $result = [];
        foreach (array_slice($lines, 0, $rows + 1) as $line) {
            $result[] = str_getcsv($line, $delimiter, '"');
        }

        return $result;
    }
}
