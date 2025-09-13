<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class DiscordOAuthController extends Controller
{
    // Шаг 1: уводим на Discord
    public function redirect(Request $request)
    {
        /** @var AbstractProvider $provider */        // ← подсказка для Intelephense
        $provider = Socialite::driver('discord');

        return $provider
            ->scopes(['identify', 'email'])           // ← теперь IDE знает про метод
            ->redirect();
    }

    // Шаг 2: колбэк от Discord
    public function callback(Request $request)
    {
        try {
            /** @var AbstractProvider $provider */    // ← подсказка для Intelephense
            $provider = Socialite::driver('discord');

            $discord = $provider->stateless()->user(); // ← и про stateless() тоже знает
        } catch (\Throwable $e) {
            Log::warning('Discord OAuth callback failed', ['e' => $e->getMessage()]);
            return redirect()->route('login')->with('status', 'Discord login failed. Try again.');
        }

        // Поля из Discord / Socialite
        $discordId   = (string) $discord->getId();             // Snowflake ID
        $email       = $discord->getEmail();                   // может быть null
        $name        = $discord->getName();                    // может быть null
        $nickname    = $discord->getNickname();                // может быть null
        $raw         = $discord->user ?? [];                   // «сырой» массив с полями Discord
        $username    = $raw['username']     ?? $nickname ?? $name ?? null;
        $globalName  = $raw['global_name']  ?? null;  // новый глобальный ник
        $discriminator = $raw['discriminator'] ?? null; // часто '0' в новых аккаунтах
        $avatarHash  = $raw['avatar']       ?? null;   // хэш аватара (без URL)

        // Нормализуем отображаемое имя
        $displayName = $globalName ?: $username ?: $name ?: ('discord_' . $discordId);

        if (Auth::check()) {
            // ==== LINK FLOW: пользователь залогинен — привязываем ====
            $user = $request->user();

            // Запретим привязку одного Discord к разным аккаунтам
            $exists = User::where('discord_user_id', $discordId)
                ->where('id', '!=', $user->id)
                ->first();

            if ($exists) {
                return redirect()->route('dashboard')
                    ->with('toast', 'Этот Discord уже привязан к другому аккаунту.');
            }

            $user->discord_user_id = $discordId;
            $user->discord_username = $username ?? $displayName;
            $user->discord_avatar = $avatarHash; // храним hash; URL соберёшь на фронте
            $user->save();

            return redirect()->route('dashboard')->with('toast', 'Discord успешно привязан.');
        }

        // ==== GUEST FLOW: пользователь гость — логиним/создаём ====

        // 1) Если уже есть аккаунт с таким discord_user_id — логиним его
        $user = User::where('discord_user_id', $discordId)->first();
        if ($user) {
            Auth::login($user, true);
            return redirect()->intended(route('dashboard'));
        }

        // 2) Если есть email и он совпадает с существующим пользователем — привязываем к нему
        if ($email) {
            $userByEmail = User::where('email', $email)->first();
            if ($userByEmail) {
                // Если у этого аккаунта уже был другой Discord — опционально можно запретить/попросить отвязать
                if ($userByEmail->discord_user_id && $userByEmail->discord_user_id !== $discordId) {
                    return redirect()->route('login')->with('status', 'Этот email уже связан с другим Discord.');
                }

                $userByEmail->discord_user_id = $discordId;
                $userByEmail->discord_username = $username ?? $displayName;
                $userByEmail->discord_avatar = $avatarHash;

                // Если у Discord почта верифицирована, можно отметить email_verified_at
                $verified = (bool) ($raw['verified'] ?? false);
                if ($verified && !$userByEmail->email_verified_at) {
                    $userByEmail->email_verified_at = now();
                }

                $userByEmail->save();

                Auth::login($userByEmail, true);
                return redirect()->intended(route('dashboard'));
            }
        }

        // 3) Иначе — создаём нового пользователя
        $generatedEmail = $email ?: ("discord_{$discordId}@example.invalid");
        $baseName = $displayName;

        // гарантируем уникальность name
        $uniqueName = $this->uniqueName($baseName);

        $user = User::create([
            'name'  => $uniqueName,
            'email' => $generatedEmail,
            'password' => bcrypt(Str::random(32)), // случайный пароль
            'full_name' => $displayName,
            'role' => User::ROLE_USER,
            'discord_user_id' => $discordId,
            'discord_username' => $username ?? $displayName,
            'discord_avatar' => $avatarHash,
            // Email сразу верифицируем ТОЛЬКО если пришёл реальный email и verified=true
            'email_verified_at' => ($email && ($raw['verified'] ?? false)) ? now() : null,
        ]);

        Auth::login($user, true);
        return redirect()->intended(route('dashboard'));
    }

    // Отвязка
    public function unlink(Request $request)
    {
        $user = $request->user();

        $user->discord_user_id = null;
        $user->discord_username = null;
        $user->discord_avatar = null;
        $user->save();

        return back()->with('toast', 'Discord отвязан.');
    }

    /**
     * Подбирает уникальное поле name для пользователя.
     */
    private function uniqueName(string $base): string
    {
        $name = Str::limit(trim($base) ?: 'user', 50, '');
        $try = $name;
        $i = 2;

        while (User::where('name', $try)->exists()) {
            $try = Str::limit($name, 40, '') . '_' . $i;
            $i++;
            if ($i > 9999) {
                $try = 'user_' . Str::random(6);
                break;
            }
        }
        return $try;
    }
}
