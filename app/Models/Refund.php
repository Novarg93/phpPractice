<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Refund extends Model
{
    protected $fillable = [
        'order_id','amount_cents','status','reason','meta','created_by','event_type',
    ];

    protected $casts = ['meta' => 'array','provider_payload' => 'array', 'event_type'       => 'string',];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(RefundItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}