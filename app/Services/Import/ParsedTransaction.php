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
        return hash('sha256', $date.'|'.number_format($amount, 2, '.', '').'|'.$reference);
    }

    /**
     * Identical rows in one file (e.g. two equal card payments on the same day)
     * share a hash. Later occurrences get a counter suffix so every row stays
     * importable, while re-importing the same file still yields the same hashes.
     *
     * @param  list<ParsedTransaction>  $transactions
     * @return list<ParsedTransaction>
     */
    public static function disambiguateHashes(array $transactions): array
    {
        $occurrences = [];

        return array_map(function (ParsedTransaction $transaction) use (&$occurrences) {
            if ($transaction->hash === null) {
                return $transaction;
            }

            $occurrence = $occurrences[$transaction->hash] = ($occurrences[$transaction->hash] ?? 0) + 1;
            if ($occurrence === 1) {
                return $transaction;
            }

            return new ParsedTransaction(
                date: $transaction->date,
                amount: $transaction->amount,
                description: $transaction->description,
                counterparty: $transaction->counterparty,
                reference: $transaction->reference,
                hash: hash('sha256', $transaction->hash.'|'.$occurrence),
                rawData: $transaction->rawData,
            );
        }, $transactions);
    }
}
