<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\LogoutController;
use App\Http\Controllers\Admin\Auth\MeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:login')
    ->name('admin.auth.login');

Route::middleware(['auth:sanctum', 'ability:admin', 'role:admin'])->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('admin.auth.logout');
    Route::get('/auth/me', MeController::class)->name('admin.auth.me');

    Route::get('/dashboard', DashboardController::class)->name('admin.dashboard');

    Route::get('/users/meta', [UserController::class, 'meta'])->name('admin.users.meta');
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
});
