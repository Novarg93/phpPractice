<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;


class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'price_cents',
        'is_active',
        'track_inventory',
        'stock',
        'image',
        'short',
        'description',
        'meta'
    ];

    protected $casts = [
        'is_active' => 'bool',
        'track_inventory' => 'bool',
        'meta' => 'array',
    ];

    // старая «главная категория»
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // новая many-to-many
    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['is_primary', 'position'])
            ->withTimestamps();
    }

    protected function image(): Attribute
    {
        return Attribute::get(
            fn($value) => $value
                ? (str_starts_with($value, 'http') || str_starts_with($value, '/')
                    ? $value
                    : Storage::url($value))
                : null
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeAvailable($q, int $qty = 1)
    {
        return $q->where(function ($q) use ($qty) {
            $q->where('track_inventory', false)->orWhere('stock', '>=', $qty);
        });
    }
}
