<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'avatar',   // путь в storage, например "avatars/xxx.jpg"
        'role',

        // ↓ опционально: если планируешь update([...]) через mass assignment
        'discord_user_id',
        'discord_username',
        'discord_avatar',

        'telegram_user_id',
        'telegram_username',
        'telegram_chat_id',
        'telegram_link_code_hash',
        'telegram_link_expires_at',
        'telegram_photo_url',

        'google_user_id',
        'google_email',
        'google_avatar',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ↓ ДОБАВИЛИ: чтобы на фронт уходили все нужные URL
    protected $appends = [
        'avatar_url',
        'discord_avatar_url',
        'telegram_avatar_url',
    ];

    /** Аватар из собственного upload (FileUpload) */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            $raw = $attributes['avatar'] ?? null; // путь или null
            if (!$raw) return null;

            // если уже абсолютный/корневой URL — вернуть как есть
            if (str_starts_with($raw, 'http') || str_starts_with($raw, '/')) {
                return $raw;
            }
            return Storage::url($raw); // "avatars/xx.jpg" -> "/storage/avatars/xx.jpg"
        });
    }

    /** Аватар Discord (CDN) */
    protected function discordAvatarUrl(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            if (!empty($attributes['discord_user_id']) && !empty($attributes['discord_avatar'])) {
                return "https://cdn.discordapp.com/avatars/{$attributes['discord_user_id']}/{$attributes['discord_avatar']}.png?size=128";
            }
            return null;
        });
    }

    /** ↓ ДОБАВИЛИ: Аватар Telegram (из photo_url, приходит из виджета) */
    protected function telegramAvatarUrl(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            return $attributes['telegram_photo_url'] ?? null;
        });
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            // ↓ ДОБАВИЛИ: чтобы удобнее работать со сроком кода привязки
            'telegram_link_expires_at' => 'datetime',
        ];
    }
}
