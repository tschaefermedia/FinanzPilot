<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['description', 'amount', 'category_id', 'frequency', 'next_due_date', 'is_active', 'auto_generate', 'account_id'])]
class RecurringTemplate extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'next_due_date' => 'date', 'is_active' => 'boolean', 'auto_generate' => 'boolean'];
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
