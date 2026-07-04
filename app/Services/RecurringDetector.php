<?php

namespace App\Services;

use App\Models\RecurringTemplate;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecurringDetector
{
    /**
     * Detect likely recurring payments from recent transactions that are not
     * already covered by an active template.
     *
     * @return array<int, array{description:string, counterparty:?string, amount:float, frequency:string, next_due_date:string, category_id:?int, occurrences:int}>
     */
    public function detect(int $months = 6, int $minOccurrences = 3): array
    {
        $since = now()->subMonths($months)->startOfMonth();

        $transactions = Transaction::query()
            ->where('date', '>=', $since->toDateString())
            ->where('source', '!=', 'recurring')
            ->orderBy('date')
            ->get(['id', 'date', 'amount', 'description', 'counterparty', 'category_id']);

        // Group by normalized payee + rounded amount (1€ bucket).
        $groups = [];
        foreach ($transactions as $t) {
            $key = $this->normalizeKey($t->counterparty ?: $t->description).'|'.round(abs((float) $t->amount));
            $groups[$key][] = $t;
        }

        $existing = RecurringTemplate::where('is_active', true)->get();
        $suggestions = [];

        foreach ($groups as $items) {
            if (count($items) < $minOccurrences) {
                continue;
            }

            $frequency = $this->gapToFrequency($this->medianGapDays($items));
            if ($frequency === null) {
                continue;
            }

            $last = end($items);
            $amount = round(array_sum(array_map(fn ($t) => (float) $t->amount, $items)) / count($items), 2);
            $description = $last->description;
            $counterparty = $last->counterparty;

            if ($this->alreadyCovered($existing, $description, $counterparty, $amount)) {
                continue;
            }

            $suggestions[] = [
                'description' => $description,
                'counterparty' => $counterparty,
                'amount' => $amount,
                'frequency' => $frequency,
                'next_due_date' => $this->advance(Carbon::parse($last->date), $frequency)->toDateString(),
                'category_id' => $this->mostCommonCategory($items),
                'occurrences' => count($items),
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        return $suggestions;
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\d+/', '', $value);      // strip dates/refs

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @param  array<int, Transaction>  $items
     */
    private function medianGapDays(array $items): int
    {
        $dates = array_map(fn ($t) => Carbon::parse($t->date), $items);
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = (int) $dates[$i - 1]->diffInDays($dates[$i]);
        }

        if (empty($gaps)) {
            return 0;
        }

        sort($gaps);

        return $gaps[intdiv(count($gaps), 2)];
    }

    private function gapToFrequency(int $gap): ?string
    {
        return match (true) {
            $gap >= 5 && $gap <= 9 => 'weekly',
            $gap >= 25 && $gap <= 35 => 'monthly',
            $gap >= 80 && $gap <= 100 => 'quarterly',
            $gap >= 350 && $gap <= 380 => 'yearly',
            default => null,
        };
    }

    private function advance(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonth(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly' => $date->copy()->addYear(),
        };
    }

    /**
     * @param  array<int, Transaction>  $items
     */
    private function mostCommonCategory(array $items): ?int
    {
        $counts = [];
        foreach ($items as $t) {
            if ($t->category_id) {
                $counts[$t->category_id] = ($counts[$t->category_id] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return null;
        }

        arsort($counts);

        return (int) array_key_first($counts);
    }

    private function alreadyCovered(Collection $existing, string $description, ?string $counterparty, float $amount): bool
    {
        $needle = $this->normalizeKey($counterparty ?: $description);

        return $existing->contains(function (RecurringTemplate $t) use ($needle, $amount) {
            $hay = $this->normalizeKey($t->description);
            $similarText = $needle !== '' && (str_contains($hay, $needle) || str_contains($needle, $hay));
            $similarAmount = abs(abs((float) $t->amount) - abs($amount)) <= max(1, abs($amount) * 0.05);

            return $similarText && $similarAmount;
        });
    }
}
