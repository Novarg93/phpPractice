<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class GoogleOAuthController extends Controller
{
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $google */
        $google = Socialite::driver('google');

        $g = $google->stateless()->user();

        // === ВОТ ЗДЕСЬ добавляем проверку верификации email из сырого ответа ===
        $raw = $g->user; // массив raw-данных Google
        $isVerified = (bool)($raw['email_verified'] ?? $raw['verified_email'] ?? false);

        $googleId = $g->getId();
        $email    = $g->getEmail();
        $name     = $g->getName() ?: $g->getNickname() ?: 'User';
        $avatar   = $g->getAvatar();

        // 1) Привязка Google для уже залогиненного
        /** @var User|null $u */
        $u = Auth::user();
        if ($u) {
            $data = [
                'google_user_id' => $googleId,
                'google_email'   => $email,
                'google_avatar'  => $avatar,
            ];
            // Если email совпадает и ещё не верифицирован, а у Google verified — верифицируем
            if ($email && $u->email === $email && ! $u->hasVerifiedEmail() && $isVerified) {
                $data['email_verified_at'] = now();
            }

            $u->forceFill($data)->save();

            return redirect()
                ->route('profile.edit')
                ->with('status', 'Google connected.');
        }

        // 2) Логин по google_user_id
        if ($existing = User::where('google_user_id', $googleId)->first()) {
            Auth::login($existing, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 3) Мерж по email
        if ($email && ($byEmail = User::where('email', $email)->first())) {
            /** @var User $byEmail */
            $byEmail->forceFill([
                'google_user_id'    => $googleId,
                'google_email'      => $email,
                'google_avatar'     => $avatar,
                // Если ещё не было verified — ставим now() только если Google подтвердил
                'email_verified_at' => $byEmail->email_verified_at ?: ($isVerified ? now() : null),
            ])->save();

            Auth::login($byEmail, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 4) Регистрация нового пользователя
        $new = User::create([
            'name'              => $name,
            'email'             => $email ?: "user{$googleId}@example.invalid",
            'password'          => Str::random(32), // захешируется через casts
            'email_verified_at' => $isVerified ? now() : null, // <— используем флаг
            'google_user_id'    => $googleId,
            'google_email'      => $email,
            'google_avatar'     => $avatar,
        ]);

        event(new Registered($new));
        Auth::login($new, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    public function unlink(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'google_user_id' => null,
            'google_email'   => null,
            'google_avatar'  => null,
        ])->save();

        return back()->with('status', 'Google disconnected.');
    }
}
