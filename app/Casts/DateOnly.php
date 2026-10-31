<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Like the "date" cast, but stores "Y-m-d" instead of "Y-m-d 00:00:00", so
 * string comparisons such as `where('date', '<=', '2026-10-31')` include that day.
 */
class DateOnly implements CastsAttributes
{
    /**
     * Return a fresh instance on every access like the built-in cast, so that
     * `$model->date->addMonth()` does not silently change the model.
     */
    public bool $withoutObjectCaching = true;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->toDateString();
    }
}
