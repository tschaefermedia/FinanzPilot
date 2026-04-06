<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'icon', 'parent_id', 'budget_monthly', 'sort_order'])]
class Category extends Model
{
    use SoftDeletes;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class, 'target_category_id');
    }

    public static function tree(): array
    {
        return static::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => $c->toTreeNode())
            ->toArray();
    }

    public function toTreeNode(): array
    {
        $node = [
            'key' => $this->id,
            'label' => $this->name,
            'data' => $this->id,
        ];

        if ($this->children && $this->children->isNotEmpty()) {
            $node['children'] = $this->children
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->map(fn ($child) => $child->toTreeNode())
                ->values()
                ->toArray();
        }

        return $node;
    }
}
