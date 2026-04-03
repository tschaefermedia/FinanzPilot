<?php

namespace App\Services\Categorization;

class CategorizationResult
{
    public function __construct(
        public readonly ?int $categoryId,
        public readonly ?int $ruleId,
        public readonly float $confidence,
        public readonly string $matchType, // 'exact', 'pattern', 'regex', 'none'
    ) {}

    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    public function isMatched(): bool
    {
        return $this->categoryId !== null;
    }
}
