<?php

namespace App\Services\Categorization;

use App\Models\CategoryRule;
use App\Models\Transaction;

class CategoryRuleEngine
{
    /**
     * Attempt to categorize a transaction using stored rules.
     * Returns the best match (highest priority, then highest confidence).
     */
    public function categorize(string $description, ?string $counterparty = null): CategorizationResult
    {
        $rules = CategoryRule::with('category')
            ->orderByDesc('priority')
            ->orderByDesc('confidence')
            ->get();

        $bestMatch = null;
        $bestConfidence = 0;

        foreach ($rules as $rule) {
            $matched = $this->matchRule($rule, $description, $counterparty);

            if ($matched && $rule->confidence > $bestConfidence) {
                $bestMatch = $rule;
                $bestConfidence = $rule->confidence;
            }
        }

        if ($bestMatch) {
            return new CategorizationResult(
                categoryId: $bestMatch->target_category_id,
                ruleId: $bestMatch->id,
                confidence: (float) $bestMatch->confidence,
                matchType: $bestMatch->is_regex ? 'regex' : 'pattern',
            );
        }

        return new CategorizationResult(
            categoryId: null,
            ruleId: null,
            confidence: 0,
            matchType: 'none',
        );
    }

    /**
     * Categorize multiple transactions in bulk. Returns an array keyed by index.
     *
     * @param array<int, array{description: string, counterparty: ?string}> $items
     * @return array<int, CategorizationResult>
     */
    public function categorizeBulk(array $items): array
    {
        // Load rules once for all items
        $rules = CategoryRule::with('category')
            ->orderByDesc('priority')
            ->orderByDesc('confidence')
            ->get();

        $results = [];

        foreach ($items as $index => $item) {
            $bestMatch = null;
            $bestConfidence = 0;

            foreach ($rules as $rule) {
                $matched = $this->matchRule($rule, $item['description'], $item['counterparty'] ?? null);

                if ($matched && $rule->confidence > $bestConfidence) {
                    $bestMatch = $rule;
                    $bestConfidence = $rule->confidence;
                }
            }

            if ($bestMatch) {
                $results[$index] = new CategorizationResult(
                    categoryId: $bestMatch->target_category_id,
                    ruleId: $bestMatch->id,
                    confidence: (float) $bestMatch->confidence,
                    matchType: $bestMatch->is_regex ? 'regex' : 'pattern',
                );
            } else {
                $results[$index] = new CategorizationResult(
                    categoryId: null,
                    ruleId: null,
                    confidence: 0,
                    matchType: 'none',
                );
            }
        }

        return $results;
    }

    /**
     * Record a manual categorization and update/create rules accordingly.
     * Called when a user manually assigns a category to a transaction.
     */
    public function learn(Transaction $transaction, int $categoryId): void
    {
        // Find existing rule that matches this transaction for this category
        $existingRule = CategoryRule::where('target_category_id', $categoryId)
            ->get()
            ->first(fn ($rule) => $this->matchRule($rule, $transaction->description, $transaction->counterparty));

        if ($existingRule) {
            // Increase confidence and hit count for existing rule
            $newConfidence = min(1.0, $existingRule->confidence + 0.1);
            $existingRule->update([
                'confidence' => $newConfidence,
                'hit_count' => $existingRule->hit_count + 1,
            ]);
        } else {
            // Suggest a new rule based on the most distinctive part of the transaction
            // Use counterparty if available (more specific), otherwise use description keywords
            $pattern = $this->generatePattern($transaction);

            if ($pattern) {
                CategoryRule::create([
                    'pattern' => $pattern,
                    'is_regex' => false,
                    'target_category_id' => $categoryId,
                    'priority' => 0,
                    'confidence' => 0.5,
                    'hit_count' => 1,
                ]);
            }
        }
    }

    /**
     * Create a rule suggestion without applying it.
     * Returns the suggested pattern and target category, or null.
     */
    public function suggestRule(Transaction $transaction, int $categoryId): ?array
    {
        $pattern = $this->generatePattern($transaction);

        if (!$pattern) {
            return null;
        }

        // Check if a similar rule already exists
        $existing = CategoryRule::where('target_category_id', $categoryId)
            ->where('pattern', $pattern)
            ->first();

        if ($existing) {
            return null; // Rule already exists
        }

        return [
            'pattern' => $pattern,
            'is_regex' => false,
            'target_category_id' => $categoryId,
            'confidence' => 0.5,
        ];
    }

    /**
     * Confirm a rule hit — increases confidence and hit_count.
     */
    public function confirmRule(int $ruleId): void
    {
        $rule = CategoryRule::find($ruleId);
        if (!$rule) return;

        $rule->update([
            'confidence' => min(1.0, $rule->confidence + 0.05),
            'hit_count' => $rule->hit_count + 1,
        ]);
    }

    /**
     * Reject a rule hit — decreases confidence.
     */
    public function rejectRule(int $ruleId): void
    {
        $rule = CategoryRule::find($ruleId);
        if (!$rule) return;

        $newConfidence = max(0, $rule->confidence - 0.15);

        if ($newConfidence <= 0) {
            $rule->delete();
        } else {
            $rule->update(['confidence' => $newConfidence]);
        }
    }

    private function matchRule(CategoryRule $rule, string $description, ?string $counterparty): bool
    {
        $searchText = mb_strtolower($description . ' ' . ($counterparty ?? ''));
        $pattern = mb_strtolower($rule->pattern);

        if ($rule->is_regex) {
            try {
                return (bool) preg_match('~' . str_replace('~', '\\~', $rule->pattern) . '~iu', $searchText);
            } catch (\Throwable) {
                return false;
            }
        }

        return str_contains($searchText, $pattern);
    }

    /**
     * Generate a pattern from a transaction.
     * Prefers counterparty (more specific), falls back to first meaningful word from description.
     */
    private function generatePattern(Transaction $transaction): ?string
    {
        // Use counterparty if available and meaningful
        if ($transaction->counterparty) {
            $counterparty = trim($transaction->counterparty);
            // Clean up common suffixes
            $cleaned = preg_replace('/\s+(gmbh|ag|e\.v\.|kg|ohg|ug|mbh|co\.?)\s*$/i', '', $counterparty);
            if (mb_strlen($cleaned) >= 3) {
                return mb_strtolower($cleaned);
            }
        }

        // Fall back to extracting keywords from description
        $description = mb_strtolower($transaction->description);

        // Remove common banking noise words
        $noise = ['lastschrift', 'gutschrift', 'überweisung', 'dauerauftrag', 'kartenzahlung',
                   'ec', 'visa', 'mastercard', 'online', 'banking', 'kto', 'blz', 'iban',
                   'end-to-end', 'ref', 'datum', 'mandat', 'glaeubiger'];
        $words = preg_split('/[\s\/\-,;:.]+/', $description);
        $words = array_filter($words, function ($word) use ($noise) {
            return mb_strlen($word) >= 3 && !in_array($word, $noise) && !is_numeric($word);
        });

        $meaningful = array_values($words);
        if (!empty($meaningful)) {
            return $meaningful[0]; // Return first meaningful word
        }

        return null;
    }
}
