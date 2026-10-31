<?php

namespace App\Models;

use App\Casts\DateOnly;
use Database\Factories\ScheduledPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['description', 'amount', 'date', 'category_id', 'account_id', 'is_completed', 'notes'])]
class ScheduledPayment extends Model
{
    /** @use HasFactory<ScheduledPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'amount' => 'decimal:2',
            'is_completed' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
