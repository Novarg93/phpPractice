<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;


class Product extends Model
{
    protected $fillable = [
    'category_id',
    'name',
    'slug',
    'sku',
    'price_cents',
    'price_preview',  
    'is_active',
    'track_inventory',
    'stock',
    'image',
    'short',
    'description',
    'meta',
];

    protected $casts = [
        'is_active' => 'bool',
        'track_inventory' => 'bool',
        'meta' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
{
    return $this->image ? Storage::url($this->image) : null;
}

    // старая «главная категория»
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted()
    {
        static::saved(function (Product $product) {
            if ($product->category_id) {
                $product->categories()->syncWithoutDetaching([
                    $product->category_id => ['is_primary' => true],
                ]);
            }
        });
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
        return Attribute::make(
            // При получении из БД — просто отдай как есть (относительный путь)
            get: fn($value) => $value,

            // При сохранении — отрежь префикс /storage/
            set: fn($value) =>
            $value
                ? ltrim(str_replace('/storage/', '', $value), '/')
                : null,
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
    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class)->orderBy('position');
    }
}
