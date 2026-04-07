<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['health_score', 'health_trend', 'structured_data', 'snapshot_hash', 'provider'])]
class AiInsight extends Model
{
    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
            'health_score' => 'integer',
        ];
    }
}
