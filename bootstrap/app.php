<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
      ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->validateCsrfTokens(
            except: [
                'stripe/webhook',     // без ведущего /
                '*/stripe/webhook',   // если есть локали/префиксы
            ]
        );

        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // ⏰ наша команда будет выполняться каждый час
        $schedule->command('orders:cancel-stale')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
