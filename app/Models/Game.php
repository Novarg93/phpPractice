<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['name','slug','description','image_url'];

    public function categories(): HasMany { return $this->hasMany(Category::class); }

    // Биндинг по slug для URL /games/{game:slug}
    public function getRouteKeyName(): string { return 'slug'; }
}
