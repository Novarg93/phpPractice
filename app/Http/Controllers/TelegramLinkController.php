<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TelegramLinkController extends Controller
{
    private function makeCode(): string
    {
        // Читабельный 8-символьный код без похожих символов
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        return collect(range(1, 8))
            ->map(fn() => $alphabet[random_int(0, strlen($alphabet)-1)])
            ->implode('');
    }

    private function expireAt(): Carbon
    {
        return now()->addMinutes(30);
    }

    public function start(Request $r)
    {
        $u = $r->user();

        // Если уже привязан chat_id, просто вернём статус
        if ($u->telegram_chat_id) {
            return back()->with('toast', 'Telegram already linked');
        }

        $code = $this->makeCode();
        $u->telegram_link_code_hash = Hash::make($code);
        $u->telegram_link_expires_at = $this->expireAt();
        $u->save();

        // Покажем пользователю сам код (в сессию/флеш)
        return back()->with([
            'tg_code' => $code,
            'toast'   => 'Telegram link code generated',
        ]);
    }

    public function refresh(Request $r)
    {
        $u = $r->user();

        if ($u->telegram_chat_id) {
            return back()->with('toast', 'Telegram already linked');
        }

        $code = $this->makeCode();
        $u->telegram_link_code_hash = Hash::make($code);
        $u->telegram_link_expires_at = $this->expireAt();
        $u->save();

        return back()->with([
            'tg_code' => $code,
            'toast'   => 'Telegram link code refreshed',
        ]);
    }

    public function unlink(Request $r)
    {
        $u = $r->user();
        $u->telegram_chat_id = null;
        $u->telegram_user_id = null;
        $u->telegram_username = null;
        $u->telegram_link_code_hash = null;
        $u->telegram_link_expires_at = null;
        $u->save();

        return back()->with('toast', 'Telegram unlinked');
    }

    /**
     * Вызывается ботом после /link <CODE>.
     * ТРЕБУЕТ дополнительной защиты (подпись/HMAC), здесь опущено ради краткости.
     *
     * Expected payload:
     * - code
     * - tg_chat_id
     * - tg_user_id
     * - tg_username (optional)
     * - app_user_id (наш ID юзера — бот узнает его из кода или из команды, как решите)
     */
    public function botConfirm(Request $r)
    {
        $data = $r->validate([
            'app_user_id' => ['required','integer','exists:users,id'],
            'code'        => ['required','string'],
            'tg_chat_id'  => ['required','string','max:32'],
            'tg_user_id'  => ['required','string','max:32'],
            'tg_username' => ['nullable','string','max:64'],
        ]);

        $u = \App\Models\User::findOrFail($data['app_user_id']);

        // Проверяем актуальность кода
        if (!$u->telegram_link_code_hash || !$u->telegram_link_expires_at || now()->gt($u->telegram_link_expires_at)) {
            throw ValidationException::withMessages(['code' => 'Code expired or not requested']);
        }

        if (!Hash::check($data['code'], $u->telegram_link_code_hash)) {
            throw ValidationException::withMessages(['code' => 'Invalid code']);
        }

        // Всё ок — связываем
        $u->telegram_chat_id = $data['tg_chat_id'];
        $u->telegram_user_id = $data['tg_user_id'];
        $u->telegram_username = $data['tg_username'] ?? $u->telegram_username;

        // Сбрасываем код
        $u->telegram_link_code_hash = null;
        $u->telegram_link_expires_at = null;

        $u->save();

        return response()->json(['status' => 'ok']);
    }
}