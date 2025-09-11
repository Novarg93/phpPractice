<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('user:discord {--id=} {--email=}', function () {
    $id    = $this->option('id');
    $email = $this->option('email');

    $user = null;
    if ($id)    $user = User::find($id);
    if (!$user && $email) $user = User::where('email', $email)->first();

    if (!$user) {
        $this->error('User not found. Use --id= or --email=');
        return 1;
    }

    $avatarUrl = ($user->discord_user_id && $user->discord_avatar)
        ? "https://cdn.discordapp.com/avatars/{$user->discord_user_id}/{$user->discord_avatar}.png?size=128"
        : null;

    $this->table(
        ['id','email','discord_user_id','discord_username','discord_avatar','discord_avatar_url'],
        [[
            $user->id,
            $user->email,
            $user->discord_user_id ?? '—',
            $user->discord_username ?? '—',
            $user->discord_avatar ?? '—',
            $avatarUrl ?? '—',
        ]]
    );

    return 0;
})->describe('Show Discord fields for a user by --id or --email');

Artisan::command('user:discord:unlink {--id=} {--email=}', function () {
    $id    = $this->option('id');
    $email = $this->option('email');

    $user = null;
    if ($id)    $user = User::find($id);
    if (!$user && $email) $user = User::where('email', $email)->first();

    if (!$user) {
        $this->error('User not found. Use --id= or --email=');
        return 1;
    }

    $user->forceFill([
        'discord_user_id' => null,
        'discord_username' => null,
        'discord_avatar' => null,
    ])->save();

    $this->info("Discord unlinked for user #{$user->id} ({$user->email}).");
    return 0;
})->describe('Unlink Discord for a user by --id or --email');