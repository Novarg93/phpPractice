<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['game_id','name','slug','type','description'];

    public function game(): BelongsTo { return $this->belongsTo(Game::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }

    public function getRouteKeyName(): string { return 'slug'; }
}
