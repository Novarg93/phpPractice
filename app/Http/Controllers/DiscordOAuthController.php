<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordOAuthController extends Controller
{
    /**
     * Вспомогательный клиент с настройками под Windows (IPv4, TLS1.2, таймауты, ретраи).
     */
    protected function httpClient(array $extra = [])
{
    return \Illuminate\Support\Facades\Http::withHeaders([
            'User-Agent' => 'Carryforce-Laravel/1.0 (+https://carryforce.local)',
        ])
        ->timeout(20)
        ->retry(1, 300)
        ->withOptions(array_merge([
            'verify' => base_path('cacert.pem'), // или абсолютный путь, типа C:\php\extras\ssl\cacert.pem
            'proxy'  => '', // отключаем наследованный системный прокси на всякий
            'curl' => [
                CURLOPT_IPRESOLVE     => CURL_IPRESOLVE_V4,
                CURLOPT_SSLVERSION    => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_HTTP_VERSION  => CURL_HTTP_VERSION_1_1, // выключаем HTTP/2 — с Cloudflare иногда решает
            ],
        ], $extra));
}

    public function redirect(Request $r): RedirectResponse
    {
        // генерируем и сохраняем state в сессии
        $state = bin2hex(random_bytes(16));
        $r->session()->put('discord_oauth_state', $state);

        $params = [
            'client_id'     => config('services.discord.client_id'),
            'redirect_uri'  => config('services.discord.redirect'),
            'response_type' => 'code',
            'scope'         => 'identify',
            'state'         => $state,
            'prompt'        => 'consent',
        ];

        $authorizeUrl = config('services.discord.authorize_url') . '?' . http_build_query($params);

        return redirect()->away($authorizeUrl);
    }

    public function callback(Request $r)
    {
        // 1) базовые проверки
        $code  = (string) $r->query('code', '');
        $state = (string) $r->query('state', '');
        abort_unless($code !== '', 400, 'Missing code');

        // сверяем state
        $savedState = (string) $r->session()->pull('discord_oauth_state', '');
    if ($savedState === '' || !hash_equals($savedState, $state)) {
        Log::warning('Discord OAuth invalid state', ['got' => $state, 'expected' => $savedState]);
        return redirect()->route('dashboard')->with('toast', 'Discord: невалидный state, попробуй ещё раз.');
    }

        $http = $this->httpClient()->asForm();

        // 2) обмен кода на токен
        try {
            $tokenResp = $http->post(config('services.discord.token_url'), [
                'client_id'     => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type'    => 'authorization_code',
                'code'          => (string) $code,
                'redirect_uri'  => config('services.discord.redirect'),
            ]);
        } catch (ConnectionException $e) {
            Log::error('Discord token HTTP failed', ['e' => $e->getMessage()]);
            return redirect()->route('dashboard')
                ->with('toast', 'Discord: нет соединения (timeout). Проверь интернет/фаервол и попробуй ещё раз.');
        }

        if (!$tokenResp->ok()) {
            // частый кейс: invalid_grant (устаревший/повторно использованный code)
            $err = $tokenResp->json('error') ?? $tokenResp->status();
            Log::warning('Discord token exchange failed', ['error' => $tokenResp->json(), 'status' => $tokenResp->status()]);
            return redirect()->route('dashboard')
                ->with('toast', "Discord OAuth ошибка: {$err}. Нажми «Link Discord» ещё раз.");
        }

        $accessToken = $tokenResp->json('access_token');
        if (!$accessToken) {
            Log::warning('Discord token response without access_token', ['body' => $tokenResp->json()]);
            return redirect()->route('dashboard')->with('toast', 'Discord: не получили access_token.');
        }

        // 3) берём профиль
        try {
            $meResp = $this->httpClient()
                ->withToken($accessToken)
                ->get(config('services.discord.api_base') . '/users/@me');
        } catch (ConnectionException $e) {
            Log::error('Discord /users/@me HTTP failed', ['e' => $e->getMessage()]);
            return redirect()->route('dashboard')
                ->with('toast', 'Discord: нет соединения (profile). Попробуй ещё раз.');
        }

        if (!$meResp->ok()) {
            Log::warning('Discord profile fetch failed', ['status' => $meResp->status(), 'body' => $meResp->json()]);
            return redirect()->route('dashboard')->with('toast', 'Discord: не удалось получить профиль.');
        }

        $me = $meResp->json();

        // 4) сохраняем данные в пользователе
        $user = $r->user();
        $user->discord_user_id = (string)($me['id'] ?? '');
        $user->discord_username = $me['global_name'] ?? ($me['username'] ?? null);
        $user->discord_avatar = $me['avatar'] ?? null;
        $user->save();

        return redirect()->route('dashboard')->with('toast', 'Discord connected!');
    }

    public function unlink(Request $r)
    {
        $u = $r->user();
        $u->discord_user_id = null;
        $u->discord_username = null;
        $u->discord_avatar = null;
        $u->save();

        return back()->with('toast', 'Discord unlinked');
    }
}