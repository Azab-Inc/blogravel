<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DraftController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PublicReadController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\SoroWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscribeController;
use App\Http\Middleware\EnsureApiKeyHasAbility;
use Illuminate\Support\Facades\Route;

// Public reads — no API key required; tenant resolved from Host header or ?tenant=
Route::prefix('v1/public')->group(function () {
    Route::get('/{resource}', [PublicReadController::class, 'index'])
        ->whereIn('resource', ['posts', 'pages', 'categories', 'tags'])
        ->name('api.v1.public.index');

    Route::get('/{resource}/{id}', [PublicReadController::class, 'show'])
        ->whereIn('resource', ['posts', 'pages', 'categories', 'tags'])
        ->name('api.v1.public.show');
});

// Public subscribe endpoints — tenant resolved from Host header
Route::prefix('v1')->group(function () {
    Route::post('/subscribe', [SubscribeController::class, 'subscribe'])
        ->name('api.subscribe');

    Route::get('/confirm/{token}', [SubscribeController::class, 'confirm'])
        ->name('api.confirm');

    Route::get('/unsubscribe/{token}', [SubscribeController::class, 'unsubscribe'])
        ->name('api.unsubscribe');

    // Webhook endpoints — HMAC verified
    Route::post('/webhooks/soro', SoroWebhookController::class)
        ->middleware('webhook.signature:soro_secret')
        ->name('api.webhooks.soro');

    // Stripe webhook — raw body, no auth
    Route::post('/webhooks/stripe', StripeWebhookController::class)
        ->withoutMiddleware([EnsureApiKeyHasAbility::class])
        ->name('api.webhooks.stripe');
});

// Authenticated routes — require API key
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.login');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.v1.logout');

    // Posts
    Route::apiResource('posts', PostController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('posts', PostController::class)
        ->middleware(['api.key.ability:write', 'plan.limit:posts'])
        ->only(['store', 'update', 'destroy']);

    // Pages
    Route::apiResource('pages', PageController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('pages', PageController::class)
        ->middleware('api.key.ability:write')
        ->only(['store', 'update', 'destroy']);

    // Categories
    Route::apiResource('categories', CategoryController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('categories', CategoryController::class)
        ->middleware('api.key.ability:write')
        ->only(['store', 'update', 'destroy']);

    // Tags
    Route::apiResource('tags', TagController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('tags', TagController::class)
        ->middleware('api.key.ability:write')
        ->only(['store', 'update', 'destroy']);

    // Drafts
    Route::get('/drafts', [DraftController::class, 'index'])
        ->middleware('api.key.ability:draft_read')
        ->name('api.v1.drafts');

    Route::get('/drafts/{id}', [DraftController::class, 'show'])
        ->middleware('api.key.ability:draft_read')
        ->name('api.v1.drafts.show');

    Route::get('/drafts/{id}/preview', [DraftController::class, 'showBySignedUrl'])
        ->name('api.v1.drafts.preview');

    Route::post('/drafts/{id}/preview-url', [DraftController::class, 'createSignedUrl'])
        ->middleware('api.key.ability:write')
        ->name('api.v1.drafts.preview-url');
});
