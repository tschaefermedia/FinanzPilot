<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['date', 'amount', 'description', 'counterparty', 'category_id', 'source', 'reference', 'hash', 'notes', 'import_batch_id', 'account_id'])]
class Transaction extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function loanPayment(): HasOne
    {
        return $this->hasOne(LoanPayment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The opposite leg of a transfer between two accounts. Both legs point at each other.
     */
    public function transferCounterpart(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transfer_transaction_id');
    }

    public function isTransferLeg(): bool
    {
        return $this->transfer_transaction_id !== null;
    }
}
