<?php

use App\Http\Controllers\PublicSite\DiscoverCheckController;
use App\Http\Controllers\PublicSite\SiteAuditController;
use Illuminate\Support\Facades\Route;

Route::post('/site-audits', [SiteAuditController::class, 'store'])
    ->middleware('throttle:site-audit')
    ->name('public.api.site-audits.store');

Route::match(['get', 'post'], '/discover-checks', [DiscoverCheckController::class, 'store'])
    ->middleware('throttle:discover-check')
    ->name('public.api.discover-checks.store');
