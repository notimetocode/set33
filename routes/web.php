<?php

use App\Http\Controllers\Admin\SpaController as AdminSpaController;
use App\Http\Controllers\App\SpaController as AppSpaController;
use App\Http\Controllers\PublicSite\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');

Route::get('/app/{any?}', AppSpaController::class)
    ->where('any', '.*')
    ->name('app.spa');

Route::get('/admin/{any?}', AdminSpaController::class)
    ->where('any', '.*')
    ->name('admin.spa');
