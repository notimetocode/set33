<?php

use App\Http\Controllers\App\Auth\LoginController;
use App\Http\Controllers\App\Auth\LogoutController;
use App\Http\Controllers\App\Auth\MeController;
use App\Http\Controllers\App\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:login')
    ->name('app.auth.login');

Route::middleware(['auth:sanctum', 'ability:app'])->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('app.auth.logout');
    Route::get('/auth/me', MeController::class)->name('app.auth.me');

    Route::get('/profile', [ProfileController::class, 'show'])->name('app.profile.show');
});
