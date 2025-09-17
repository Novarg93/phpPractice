<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use App\Models\Page;
use Illuminate\Support\Facades\Schema;





class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\Payments\RefundProvider::class, function ($app) {
            if (app()->environment('production') && config('services.stripe.use_live_refunds')) {
                return new \App\Services\Payments\StripeRefundProvider(
                    new \Stripe\StripeClient(config('services.stripe.secret'))
                );
            }
            return new \App\Services\Payments\FakeRefundProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Inertia::share('social', fn() => [
            'telegram_bot' => env('TELEGRAM_BOT_USERNAME'),
        ]);

        Inertia::share('legalPages', function () {
            if (! Schema::hasTable('pages')) return collect();

            return Page::query()
                ->select('id', 'name', 'code')
                ->whereNotNull('code')
                ->orderBy('order') // или на тот столбец, который у тебя реально есть
                ->get()
                ->map(fn(Page $p) => [
                    'id'   => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'url'  => route('legal.show', $p->code),
                ]);
        });
    }
}
