<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramAuthController extends Controller
{
    public function callback(Request $r)
    {
        $data = $r->query();
        foreach (['id','auth_date','hash'] as $f) {
            if (empty($data[$f])) {
                return redirect()->route('dashboard')->with('toast','Telegram: некорректные данные.');
            }
        }

        // data_check_string
        $check = $data; unset($check['hash']); ksort($check);
        $pairs = [];
        foreach ($check as $k => $v) { $pairs[] = $k.'='.$v; }
        $dataCheckString = implode("\n", $pairs);

        // secret_key = SHA256(bot_token)
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            return redirect()->route('dashboard')->with('toast','Telegram: сервер не настроен.');
        }
        $secretKey = hash('sha256', $botToken, true);
        $calc = hash_hmac('sha256', $dataCheckString, $secretKey);
        $hash = strtolower((string)$data['hash']);

        if (!hash_equals($calc, $hash)) {
            Log::warning('Telegram Login signature mismatch', ['calc'=>$calc,'got'=>$hash]);
            return redirect()->route('dashboard')->with('toast','Telegram: подпись не прошла.');
        }

        // устаревание (24ч)
        if ((int)$data['auth_date'] < time() - 86400) {
            return redirect()->route('dashboard')->with('toast','Telegram: авторизация устарела.');
        }

        // сохраняем привязку
        $u = $r->user();
        $tgId = (string)$data['id'];
        $tgUsername = $data['username'] ?? null;

        // запретим привязку одного TG к разным пользователям
        $exists = \App\Models\User::where('telegram_user_id', $tgId)->where('id','!=',$u->id)->first();
        if ($exists) {
            return redirect()->route('dashboard')->with('toast','Этот Telegram уже привязан к другому аккаунту.');
        }

        $u->telegram_user_id = $tgId;
        $u->telegram_username = $tgUsername;
        $u->save();

        return redirect()->route('dashboard')->with('toast','Telegram connected!');
    }

    public function unlink(Request $r)
    {
        $u = $r->user();
        $u->telegram_user_id = null;
        $u->telegram_username = null;
        $u->save();

        return back()->with('toast','Telegram unlinked');
    }
}