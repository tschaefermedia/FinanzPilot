<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'starting_balance', 'currency', 'icon', 'color', 'sort_order', 'is_active'])]
class Account extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recurringTemplates(): HasMany
    {
        return $this->hasMany(RecurringTemplate::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        return round((float) $this->starting_balance + (float) $this->transactions()->sum('amount'), 2);
    }
}
