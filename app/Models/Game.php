<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    protected $fillable = ['name','slug','description','image_url'];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    // Биндинг по slug для URL /games/{game:slug}
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Аксессор для автоматического преобразования image_url → полный URL
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function ($value) {
            if (!$value) return null;
            if (str_starts_with($value, 'http') || str_starts_with($value, '/')) {
                return $value;
            }
            return Storage::url($value); // превратит "games/xxx.jpg" → "/storage/games/xxx.jpg"
        });
    }
}

