<?php

namespace App\Models;


use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN   = 'admin';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_USER    = 'user';

    public static function roleOptions(): array
    {
        return [
            self::ROLE_USER    => 'User',
            self::ROLE_SUPPORT => 'Support',
            self::ROLE_ADMIN   => 'Admin',
        ];
    }

    protected $fillable = [
        'name',
        'full_name',
        'email',
        'password',
        'avatar',   // <- в БД ХРАНИМ ТОЛЬКО ПУТЬ, например "avatars/xxx.jpg"
        'role',
    ];

    // Аккуратный URL для фронта (не ломает FileUpload)
    protected $appends = ['avatar_url'];

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            $raw = $attributes['avatar'] ?? null;           // путь или null
            if (!$raw) return null;

            // если вдруг уже URL — вернём как есть
            if (str_starts_with($raw, 'http') || str_starts_with($raw, '/')) {
                return $raw;
            }
            return Storage::url($raw); // "avatars/xx.jpg" -> "/storage/avatars/xx.jpg"
        });
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
