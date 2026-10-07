<?php

use App\Http\Controllers\PublicSite\SiteAuditController;
use Illuminate\Support\Facades\Route;

Route::post('/site-audits', [SiteAuditController::class, 'store'])
    ->middleware('throttle:site-audit')
    ->name('public.api.site-audits.store');
