<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pattern', 'is_regex', 'target_category_id', 'priority', 'confidence', 'hit_count'])]
class CategoryRule extends Model
{
    protected function casts(): array
    {
        return ['is_regex' => 'boolean', 'confidence' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }
}
