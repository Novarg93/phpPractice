<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ContactMessage extends Model
{
    protected $fillable = [
        'first_name','last_name','email','message',
        'status','handled_by','handled_at','ip','user_agent','meta',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'meta'       => 'array',
    ];
    
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}