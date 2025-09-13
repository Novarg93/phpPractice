<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramAuthController extends Controller
{
    public function callback(Request $r)
    {
        $data = $r->query();

        // Базовая валидация наличия ключей от виджета
        foreach (['id', 'auth_date', 'hash'] as $f) {
            if (empty($data[$f])) {
                return redirect()->route('dashboard')->with('toast', 'Telegram: некорректные данные.');
            }
        }

        // 1) Проверка подписи (официальный алгоритм)
        $check = $data;
        unset($check['hash']);
        ksort($check);
        $pairs = [];
        foreach ($check as $k => $v) {
            $pairs[] = $k.'='.$v;
        }
        $dataCheckString = implode("\n", $pairs);

        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            return redirect()->route('dashboard')->with('toast', 'Telegram: сервер не настроен.');
        }

        $secretKey = hash('sha256', $botToken, true);
        $calc = hash_hmac('sha256', $dataCheckString, $secretKey);
        $hash = strtolower((string) $data['hash']);

        if (!hash_equals($calc, $hash)) {
            Log::warning('Telegram Login signature mismatch', ['calc' => $calc, 'got' => $hash]);
            return redirect()->route('dashboard')->with('toast', 'Telegram: подпись не прошла.');
        }

        // 2) Данные не старше 24 часов
        if ((int) $data['auth_date'] < time() - 86400) {
            return redirect()->route('dashboard')->with('toast', 'Telegram: авторизация устарела.');
        }

        // 3) Привязка профиля
        $u = $r->user();

        $tgId       = (string) ($data['id'] ?? '');
        $tgUsername = $data['username']   ?? null;
        $tgFirst    = $data['first_name'] ?? null;
        $tgLast     = $data['last_name']  ?? null;

        // Один и тот же Telegram — только к одному аккаунту
        $exists = \App\Models\User::where('telegram_user_id', $tgId)
            ->where('id', '!=', $u->id)
            ->first();
        if ($exists) {
            return redirect()->route('dashboard')->with('toast', 'Этот Telegram уже привязан к другому аккаунту.');
        }

        $u->telegram_user_id  = $tgId;
        $u->telegram_username = $tgUsername;

        // (опционально) Если нет ФИО — подставим из Telegram
        if (!$u->full_name && ($tgFirst || $tgLast)) {
            $u->full_name = trim(($tgFirst ?? '').' '.($tgLast ?? ''));
        }

        $u->save();

        return redirect()->route('dashboard')->with('toast', 'Telegram connected!');
    }

    public function unlink(Request $r)
    {
        $u = $r->user();

        $u->telegram_user_id = null;
        $u->telegram_username = null;
        // на всякий случай подчистим служебные поля, если они были
        $u->telegram_chat_id = null;
        $u->telegram_link_code_hash = null;
        $u->telegram_link_expires_at = null;

        $u->save();

        return back()->with('toast', 'Telegram unlinked');
    }
}
