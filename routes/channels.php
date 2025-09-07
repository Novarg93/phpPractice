<?php

use Illuminate\Support\Facades\Broadcast;

// Регистрирует POST /broadcasting/auth с 'web' + 'auth'
Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('orders', function ($user) {
    return in_array($user->role, ['support','admin'], true);
});
// Здесь можно описывать приватные/присутствия-каналы при необходимости.
// Пример (можно оставить закомментированным):
/*
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
*/