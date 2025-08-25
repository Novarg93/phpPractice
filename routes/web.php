<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GameController;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CatalogController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CartController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('cart.index');
        Route::post('/add', [CartController::class, 'add'])->name('cart.add');
        Route::post('/update', [CartController::class, 'update'])->name('cart.update');
        Route::post('/remove', [CartController::class, 'remove'])->name('cart.remove');
        Route::get('/summary', [CartController::class, 'summary'])->name('cart.summary');
    });
});

Route::get('/catalog', function() {
    return Inertia::render('Catalog');
})->name('catalog');

Route::get('/games', [GameController::class, 'index'])->name('games.index');

// общий листинг: ALL и фильтр по категории
Route::get('/games', [GameController::class, 'index'])->name('games.index');

Route::scopeBindings()->group(function () {
    // ALL (страница игры со всеми товарами)
    Route::get('/games/{game:slug}', [CatalogController::class, 'index'])
        ->name('games.show');

    // Фильтр по категории (та же страница, но с выбранной категорией)
    Route::get('/games/{game:slug}/{category:slug}', [CatalogController::class, 'index'])
        ->name('categories.show');

    // Карточка товара
    Route::get('/games/{game:slug}/{category:slug}/{product:slug}', [ProductController::class, 'show'])
        ->scopeBindings()
        ->name('products.show');
});

require __DIR__.'/auth.php';
