<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;


class ContactMessage extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'message',
        'status',
        'handled_by',
        'handled_at',
        'ip',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'meta'       => 'array',
    ];

    protected $attributes = ['status' => 'new'];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Назначаем owner только когда статус уходит из 'new'
            if (
                $model->isDirty('status') &&
                $model->getOriginal('status') === 'new' &&
                $model->status !== 'new' &&
                empty($model->handled_by)
            ) {
                $model->handled_by = Auth::id();
            }

            // Если закрывают тикет — ставим handled_at
            if ($model->isDirty('status') && $model->status === 'done' && empty($model->handled_at)) {
                $model->handled_at = now();
            }
        });
    }
}
