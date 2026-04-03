<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'source_type', 'column_mapping'])]
class ImportMapping extends Model
{
    protected function casts(): array
    {
        return ['column_mapping' => 'array'];
    }
}
