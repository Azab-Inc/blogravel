<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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
