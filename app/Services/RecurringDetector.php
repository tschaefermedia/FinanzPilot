<?php

namespace App\Services;

use App\Models\RecurringTemplate;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecurringDetector
{
    private const DISMISSED_KEY = 'dismissed_recurring';

    /**
     * Detect likely recurring payments from recent transactions that are not
     * already covered by a template or dismissed by the user.
     *
     * @return array<int, array{signature:string, description:string, counterparty:?string, amount:float, frequency:string, next_due_date:string, category_id:?int, occurrences:int}>
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

        $templates = RecurringTemplate::all();
        $dismissed = $this->dismissedSignatures();
        $suggestions = [];

        foreach ($groups as $signature => $items) {
            if (count($items) < $minOccurrences || in_array($signature, $dismissed, true)) {
                continue;
            }

            $frequency = $this->gapToFrequency($this->medianGapDays($items));
            if ($frequency === null) {
                continue;
            }

            $last = end($items);
            $amount = round(array_sum(array_map(fn ($t) => (float) $t->amount, $items)) / count($items), 2);
            $rawDescription = (string) $last->description;
            $counterparty = $last->counterparty;

            if ($this->alreadyCovered($templates, $rawDescription, $counterparty, $amount)) {
                continue;
            }

            $suggestions[] = [
                'signature' => $signature,
                'description' => $this->cleanLabel($counterparty, $rawDescription),
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

    /**
     * Mark a suggestion signature as dismissed so it is not surfaced again.
     */
    public function dismiss(string $signature): void
    {
        $dismissed = $this->dismissedSignatures();
        if (! in_array($signature, $dismissed, true)) {
            $dismissed[] = $signature;
            Setting::set(self::DISMISSED_KEY, json_encode($dismissed));
        }
    }

    /**
     * @return array<int, string>
     */
    private function dismissedSignatures(): array
    {
        return json_decode((string) Setting::get(self::DISMISSED_KEY, '[]'), true) ?: [];
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\d+/', '', $value);      // strip dates/refs

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Build a human-readable label, preferring the (usually clean) counterparty
     * over the raw description, which is often full of reference/mandate noise.
     */
    private function cleanLabel(?string $counterparty, string $description): string
    {
        $candidate = trim((string) $counterparty) !== '' ? trim((string) $counterparty) : $description;

        foreach ([$this->stripReferenceNoise($candidate), $this->stripReferenceNoise($description), trim((string) $counterparty)] as $option) {
            if (trim($option) !== '') {
                return mb_strimwidth(trim($option), 0, 48, '…');
            }
        }

        return 'Wiederkehrende Zahlung';
    }

    private function stripReferenceNoise(string $text): string
    {
        // UUIDs
        $text = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '', $text);
        // Long alphanumeric reference/booking codes (contain at least one digit)
        $text = preg_replace('/\b(?=[0-9a-z\-]*\d)[0-9a-z\-]{8,}\b/i', '', $text);
        // Standalone long number runs
        $text = preg_replace('/\b\d{4,}\b/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text, ' -–—:•,.');
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

    /**
     * A template covers a suggestion when amounts are close and they share a
     * significant word — matched against both active and inactive templates so
     * a payment the user already tracks (or paused) is not re-suggested.
     */
    private function alreadyCovered(Collection $templates, string $description, ?string $counterparty, float $amount): bool
    {
        $needleTokens = $this->significantTokens(($counterparty ?: '').' '.$description);
        if (empty($needleTokens)) {
            return false;
        }

        return $templates->contains(function (RecurringTemplate $t) use ($needleTokens, $amount) {
            $amountClose = abs(abs((float) $t->amount) - abs($amount)) <= max(1, abs($amount) * 0.10);

            return $amountClose && ! empty(array_intersect($needleTokens, $this->significantTokens($t->description)));
        });
    }

    /**
     * @return array<int, string>
     */
    private function significantTokens(string $text): array
    {
        $text = preg_replace('/[^a-zäöüß ]/u', ' ', mb_strtolower($text));
        $noise = ['abo', 'miete', 'paypal', 'lastschrift', 'service', 'zahlung', 'monatlich', 'basic'];

        return array_values(array_filter(
            preg_split('/\s+/', trim($text)),
            fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $noise, true),
        ));
    }
}
