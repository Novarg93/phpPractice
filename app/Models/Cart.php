<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany {
        return $this->hasMany(CartItem::class);
    }

    public function totalQty(): int {
        return $this->items->sum('qty');
    }

    public function totalCents(): int {
        return $this->items->sum('line_total_cents');
    }
}