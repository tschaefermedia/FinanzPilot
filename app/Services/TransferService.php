<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links two transactions as the legs of one transfer between accounts.
 *
 * Both legs are assigned a category of type "transfer", which is what every
 * report already uses to exclude money moved between own accounts from
 * income and expenses.
 */
class TransferService
{
    public const CANDIDATE_DAY_RANGE = 5;

    public function link(Transaction $first, Transaction $second): void
    {
        $this->ensureLinkable($first, $second);

        DB::transaction(function () use ($first, $second) {
            $transferCategoryId = $this->transferCategory()->id;

            foreach ([[$first, $second], [$second, $first]] as [$leg, $counterpart]) {
                $leg->transfer_transaction_id = $counterpart->id;
                if ($leg->category?->type !== 'transfer') {
                    $leg->category_id = $transferCategoryId;
                }
                $leg->save();
            }
        });
    }

    public function unlink(Transaction $transaction): void
    {
        $counterpart = $transaction->transferCounterpart;

        DB::transaction(function () use ($transaction, $counterpart) {
            foreach (array_filter([$transaction, $counterpart]) as $leg) {
                $leg->transfer_transaction_id = null;
                // Back to uncategorized so the leg reappears in the review queue.
                if ($leg->category?->type === 'transfer') {
                    $leg->category_id = null;
                }
                $leg->save();
            }
        });
    }

    public function create(Account $fromAccount, Account $toAccount, float $amount, string $date, string $description): Transaction
    {
        if ($fromAccount->is($toAccount)) {
            throw ValidationException::withMessages(['to_account_id' => 'Quell- und Zielkonto müssen sich unterscheiden.']);
        }

        return DB::transaction(function () use ($fromAccount, $toAccount, $amount, $date, $description) {
            $legAttributes = ['date' => $date, 'description' => $description, 'source' => 'manual'];

            $outgoing = Transaction::create([...$legAttributes, 'amount' => -$amount, 'account_id' => $fromAccount->id]);
            $incoming = Transaction::create([...$legAttributes, 'amount' => $amount, 'account_id' => $toAccount->id]);

            $this->link($outgoing, $incoming);

            return $outgoing;
        });
    }

    /**
     * Unlinked transactions that could be the other leg: exact opposite amount,
     * booked on a different account, within a few days.
     *
     * @return Collection<int, Transaction>
     */
    public function candidates(Transaction $transaction): Collection
    {
        if ($transaction->account_id === null || $transaction->isTransferLeg()) {
            return new Collection;
        }

        $date = Carbon::parse($transaction->date);

        return Transaction::with('account')
            ->whereKeyNot($transaction->id)
            ->whereNull('transfer_transaction_id')
            ->whereNotNull('account_id')
            ->where('account_id', '!=', $transaction->account_id)
            // CAST(... AS REAL): SQLite binds PHP floats as text, breaking numeric comparison.
            ->whereRaw('ROUND(amount, 2) = CAST(? AS REAL)', [round(-(float) $transaction->amount, 2)])
            ->whereBetween('date', [
                $date->copy()->subDays(self::CANDIDATE_DAY_RANGE)->toDateString(),
                $date->copy()->addDays(self::CANDIDATE_DAY_RANGE)->toDateString(),
            ])
            ->get()
            ->sortBy(fn (Transaction $candidate) => abs($date->diffInDays($candidate->date)))
            ->values();
    }

    private function ensureLinkable(Transaction $first, Transaction $second): void
    {
        $error = match (true) {
            $first->is($second) => 'Eine Buchung kann nicht mit sich selbst verknüpft werden.',
            $first->isTransferLeg() || $second->isTransferLeg() => 'Eine der Buchungen ist bereits Teil einer Umbuchung.',
            $first->account_id === null || $second->account_id === null => 'Beide Buchungen müssen einem Konto zugeordnet sein.',
            $first->account_id === $second->account_id => 'Die Buchungen müssen auf unterschiedlichen Konten liegen.',
            round((float) $first->amount + (float) $second->amount, 2) !== 0.0 => 'Die Beträge müssen sich genau aufheben (z. B. -100 € und +100 €).',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['transfer' => $error]);
        }
    }

    private function transferCategory(): Category
    {
        return Category::where('type', 'transfer')->orderBy('id')->first()
            ?? Category::create(['name' => 'Übertragungen', 'type' => 'transfer', 'icon' => 'pi-arrows-h']);
    }
}
