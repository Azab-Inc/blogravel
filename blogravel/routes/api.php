<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\PostController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::apiResource('posts', PostController::class)
        ->middleware('api.key.ability:read')
        ->only(['index', 'show']);

    Route::apiResource('posts', PostController::class)
        ->middleware('api.key.ability:write')
        ->only(['store', 'update', 'destroy']);

    Route::get('/drafts', fn () => response()->json(['data' => []]))->middleware('api.key.ability:draft_read');
});
