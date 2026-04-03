<?php

namespace App\Services\Import;

class ParsedTransaction
{
    public function __construct(
        public readonly string $date,          // YYYY-MM-DD
        public readonly float $amount,          // signed (negative = expense)
        public readonly string $description,
        public readonly ?string $counterparty,
        public readonly ?string $reference,
        public readonly ?string $hash,          // SHA-256 for dedup
        public readonly array $rawData = [],    // original row for debugging
    ) {}

    public static function computeHash(string $date, float $amount, string $reference): string
    {
        return hash('sha256', $date . '|' . number_format($amount, 2, '.', '') . '|' . $reference);
    }
}
