<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Auth\Events\Login;
use App\Listeners\MergeCartOnLogin;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            MergeCartOnLogin::class,
        ],
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \SocialiteProviders\Discord\DiscordExtendSocialite::class.'@handle',
    ],
    ];
}