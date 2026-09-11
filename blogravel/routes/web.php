<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

// Theme frontend routes
Route::get('/', [ThemeController::class, 'home'])->name('theme.home');
Route::get('/post/{slug}', [ThemeController::class, 'post'])->name('theme.post');
Route::get('/category/{slug}', [ThemeController::class, 'category'])->name('theme.category');
Route::get('/subscribe', [ThemeController::class, 'subscribeForm'])->name('theme.subscribe');
Route::post('/subscribe/{tenant}', [ThemeController::class, 'subscribe'])->name('theme.subscribe.post');
Route::get('/contact', [ThemeController::class, 'contactForm'])->name('theme.contact');
Route::post('/contact/{tenant}', [ThemeController::class, 'contact'])->name('theme.contact.post');

// Feeds
Route::get('/feeds/{resource}', [FeedController::class, 'posts'])
    ->whereIn('resource', ['posts'])
    ->name('feed.posts');

Route::get('/feeds/categories/{slug}', [FeedController::class, 'category'])
    ->name('feed.category');

Route::get('/feeds/authors/{author}', [FeedController::class, 'author'])
    ->name('feed.author');

// Invitations
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->name('invitations.accept');

Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
    ->middleware('plan.limit:users')
    ->name('invitations.accept.post');

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/secret', fn () => response('ok'));
});

Route::get('/debug/session-check', function () {
    $start = microtime(true);

    $t1 = microtime(true);
    $user = auth()->user();
    $e1 = round((microtime(true) - $t1) * 1000);

    $t2 = microtime(true);
    $sessionId = session()->getId();
    $e2 = round((microtime(true) - $t2) * 1000);

    $total = round((microtime(true) - $start) * 1000);

    return response("Auth: {$e1}ms user=".($user ? $user->email : 'null').
        ", Session: {$e2}ms id={$sessionId}".
        ", Total: {$total}ms");
});
