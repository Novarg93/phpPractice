<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = ['game_id','name','slug','type','description','image'];

    public function game(): BelongsTo { return $this->belongsTo(Game::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function getRouteKeyName(): string { return 'slug'; }

    // превратить 'categories/xx.jpg' → '/storage/categories/xx.jpg'
    protected function image(): Attribute
    {
        return Attribute::get(fn ($value) => $value
            ? (str_starts_with($value, 'http') || str_starts_with($value, '/')
                ? $value
                : Storage::url($value))
            : null
        );
    }
}
