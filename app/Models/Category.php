<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'slug',
        'type',
        'description',
        'image',
    ];

    // 👇 добавляем вычисляемое поле
    protected $appends = ['image_url'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withPivot(['is_primary', 'position'])
            ->withTimestamps();
    }

    // 👇 Генерация image_url
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn ($value, $attributes) =>
                !empty($attributes['image'])
                    ? (str_starts_with($attributes['image'], 'http') || str_starts_with($attributes['image'], '/')
                        ? $attributes['image']
                        : Storage::url($attributes['image']))
                    : null
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}