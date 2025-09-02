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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        
    Inertia::share('pages', fn () =>
        Schema::hasTable('pages')
            ? \App\Models\Page::select('id', 'name', 'code')->orderBy('order')->get()
            : collect()
    );
    }
}
