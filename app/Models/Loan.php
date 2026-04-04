<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'principal', 'interest_rate', 'start_date', 'term_months', 'payment_day', 'monthly_rate', 'initial_balance', 'match_description', 'account_id', 'direction', 'notes'])]
class Loan extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'monthly_rate' => 'decimal:2',
            'initial_balance' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
