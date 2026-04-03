<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['filename', 'source_type', 'uploaded_at', 'row_count', 'status'])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
