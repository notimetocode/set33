<?php

use App\Http\Controllers\Admin\SpaController as AdminSpaController;
use App\Http\Controllers\App\GithubOAuthCallbackController;
use App\Http\Controllers\App\GoogleOAuthCallbackController;
use App\Http\Controllers\App\SpaController as AppSpaController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\PrivacyPolicyController;
use App\Http\Controllers\PublicSite\SharedAiReportController;
use App\Http\Controllers\PublicSite\TermsOfServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');

Route::get('/privacy', PrivacyPolicyController::class)->name('public.privacy');

Route::get('/terms', TermsOfServiceController::class)->name('public.terms');

Route::get('/r/{token}', [SharedAiReportController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->name('public.ai-reports.show');

Route::post('/r/{token}/unlock', [SharedAiReportController::class, 'unlock'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:ai-report-unlock')
    ->name('public.ai-reports.unlock');

Route::get('/oauth/google/callback', GoogleOAuthCallbackController::class)
    ->name('oauth.google.callback');

Route::get('/oauth/github/callback', GithubOAuthCallbackController::class)
    ->name('oauth.github.callback');

Route::get('/app/{any?}', AppSpaController::class)
    ->where('any', '.*')
    ->name('app.spa');

Route::get('/admin/{any?}', AdminSpaController::class)
    ->where('any', '.*')
    ->name('admin.spa');
