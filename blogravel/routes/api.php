<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/login', LoginController::class);
Route::post('/v1/logout', LogoutController::class)->middleware('auth:sanctum');
