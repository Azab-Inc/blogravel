<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/secret', fn () => response('ok'));
});
