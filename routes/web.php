<?php

use App\Http\Controllers\{
    ProfileController,
    GameController,
    CheckoutController,
    OrderController,
    ProductController,
    CatalogController,
    CartController,
    PageController,
    PostController,
    ContactController
};
use Illuminate\Foundation\Application;



use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', fn() => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]))->name('home');



Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Orders (доступны без verified)
    Route::get('/profile/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/profile/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

/**
 * Checkout: auth + verified
 * — только здесь требуем подтверждение email
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/session', [CheckoutController::class, 'createSession'])->name('checkout.session');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel',  [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});

// Cart (гостям доступен)
Route::prefix('cart')->group(function () {
    Route::get('/',        [CartController::class, 'index'])->name('cart.index');
    Route::post('/add',    [CartController::class, 'add'])->name('cart.add');
    Route::post('/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::get('/summary', [CartController::class, 'summary'])->name('cart.summary');
});

Route::get('/legal/{page:code}', [PageController::class, 'show'])->name('legal.show');
Route::get('/catalog', fn() => Inertia::render('Catalog'))->name('catalog');

Route::get('/contact', fn() => Inertia::render('Contact/Show'))->name('contact.show');
Route::post('/contact/send', [ContactController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('contact.send');

Route::get('/games', [GameController::class, 'index'])->name('games.index');

Route::post('/checkout/nickname', [CheckoutController::class, 'saveNickname'])->name('checkout.nickname');
Route::post('/orders/{order}/nickname', [OrderController::class, 'saveNickname'])->name('orders.nickname');

Route::scopeBindings()->group(function () {
    Route::get('/games/{game:slug}', [CatalogController::class, 'index'])->name('games.show');
    Route::get('/games/{game:slug}/{category:slug}', [CatalogController::class, 'index'])->name('categories.show');
    Route::get('/games/{game:slug}/{category:slug}/{product:slug}', [ProductController::class, 'show'])
        ->scopeBindings()->name('products.show');
});

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');

require __DIR__ . '/auth.php';