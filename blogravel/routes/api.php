<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Posts
    Route::apiResource('posts', PostController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('posts', PostController::class)
        ->middleware('api.key.ability:write')
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
    Route::get('/drafts', fn () => response()->json(['data' => []]))->middleware('api.key.ability:draft_read');
});
