<?php

use Illuminate\Support\Facades\Broadcast;

// Регистрирует POST /broadcasting/auth с 'web' + 'auth'
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Публичные каналы НЕ требуют объявлений ниже.
// Здесь можно описывать приватные/присутствия-каналы при необходимости.
// Пример (можно оставить закомментированным):
/*
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
*/