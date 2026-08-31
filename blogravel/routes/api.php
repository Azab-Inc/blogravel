<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('/posts', fn () => response()->json(['data' => []]))->middleware('api.key.ability:read');
    Route::post('/posts', fn () => response()->json(['data' => []], 201))->middleware('api.key.ability:write');
    Route::get('/drafts', fn () => response()->json(['data' => []]))->middleware('api.key.ability:draft_read');
});
