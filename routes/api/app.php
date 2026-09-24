<?php

use App\Http\Controllers\App\AiServiceController;
use App\Http\Controllers\App\Auth\LoginController;
use App\Http\Controllers\App\Auth\LogoutController;
use App\Http\Controllers\App\Auth\MeController;
use App\Http\Controllers\App\GithubConnectionController;
use App\Http\Controllers\App\GithubRepositoryController;
use App\Http\Controllers\App\GoogleConnectionController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\SiteController;
use App\Http\Controllers\App\SiteGithubIntegrationController;
use App\Http\Controllers\App\SiteGoogleIntegrationController;
use App\Http\Controllers\App\SiteMetricsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:login')
    ->name('app.auth.login');

Route::middleware(['auth:sanctum', 'ability:app'])->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('app.auth.logout');
    Route::get('/auth/me', MeController::class)->name('app.auth.me');

    Route::get('/profile', [ProfileController::class, 'show'])->name('app.profile.show');

    Route::get('/ai-services/meta', [AiServiceController::class, 'meta'])->name('app.ai-services.meta');
    Route::post('/ai-services/models', [AiServiceController::class, 'models'])
        ->middleware('throttle:ai-service-models')
        ->name('app.ai-services.models');
    Route::post('/ai-services/{ai_service}/check', [AiServiceController::class, 'check'])
        ->middleware('throttle:ai-service-check')
        ->name('app.ai-services.check');
    Route::post('/ai-services/{ai_service}/generate', [AiServiceController::class, 'generate'])
        ->middleware('throttle:ai-service-generate')
        ->name('app.ai-services.generate');
    Route::apiResource('ai-services', AiServiceController::class)->names('app.ai-services');

    Route::apiResource('sites', SiteController::class)->names('app.sites');

    Route::get('/google/connection', [GoogleConnectionController::class, 'show'])
        ->name('app.google.connection.show');
    Route::post('/google/oauth/start', [GoogleConnectionController::class, 'start'])
        ->middleware('throttle:google-oauth-start')
        ->name('app.google.oauth.start');
    Route::delete('/google/connection', [GoogleConnectionController::class, 'destroy'])
        ->name('app.google.connection.destroy');
    Route::get('/google/ga4-properties', [GoogleConnectionController::class, 'ga4Properties'])
        ->name('app.google.ga4-properties');
    Route::get('/google/gsc-sites', [GoogleConnectionController::class, 'gscSites'])
        ->name('app.google.gsc-sites');

    Route::get('/github/connection', [GithubConnectionController::class, 'show'])
        ->name('app.github.connection.show');
    Route::post('/github/oauth/start', [GithubConnectionController::class, 'start'])
        ->middleware('throttle:github-oauth-start')
        ->name('app.github.oauth.start');
    Route::delete('/github/connection', [GithubConnectionController::class, 'destroy'])
        ->name('app.github.connection.destroy');
    Route::get('/github/repositories', [GithubRepositoryController::class, 'index'])
        ->middleware('throttle:github-api')
        ->name('app.github.repositories.index');
    Route::get('/github/repositories/{owner}/{repo}/branches', [GithubRepositoryController::class, 'branches'])
        ->middleware('throttle:github-api')
        ->where(['owner' => '[A-Za-z0-9_.-]+', 'repo' => '[A-Za-z0-9_.-]+'])
        ->name('app.github.repositories.branches');
    Route::get('/github/repositories/{owner}/{repo}/commits', [GithubRepositoryController::class, 'commits'])
        ->middleware('throttle:github-api')
        ->where(['owner' => '[A-Za-z0-9_.-]+', 'repo' => '[A-Za-z0-9_.-]+'])
        ->name('app.github.repositories.commits');

    Route::get('/sites/{site}/google-integration', [SiteGoogleIntegrationController::class, 'show'])
        ->name('app.sites.google-integration.show');
    Route::put('/sites/{site}/google-integration', [SiteGoogleIntegrationController::class, 'update'])
        ->name('app.sites.google-integration.update');
    Route::delete('/sites/{site}/google-integration', [SiteGoogleIntegrationController::class, 'destroy'])
        ->name('app.sites.google-integration.destroy');
    Route::post('/sites/{site}/google-integration/sync', [SiteGoogleIntegrationController::class, 'sync'])
        ->middleware('throttle:google-metrics-sync')
        ->name('app.sites.google-integration.sync');

    Route::get('/sites/{site}/github-integration', [SiteGithubIntegrationController::class, 'show'])
        ->name('app.sites.github-integration.show');
    Route::put('/sites/{site}/github-integration', [SiteGithubIntegrationController::class, 'update'])
        ->name('app.sites.github-integration.update');
    Route::delete('/sites/{site}/github-integration', [SiteGithubIntegrationController::class, 'destroy'])
        ->name('app.sites.github-integration.destroy');
    Route::post('/sites/{site}/github-integration/sync', [SiteGithubIntegrationController::class, 'sync'])
        ->middleware('throttle:github-commits-sync')
        ->name('app.sites.github-integration.sync');

    Route::get('/sites/{site}/metrics/analytics', [SiteMetricsController::class, 'analytics'])
        ->name('app.sites.metrics.analytics');
    Route::get('/sites/{site}/metrics/search-console', [SiteMetricsController::class, 'searchConsole'])
        ->name('app.sites.metrics.search-console');
    Route::get('/sites/{site}/metrics/github-commits', [SiteMetricsController::class, 'githubCommits'])
        ->name('app.sites.metrics.github-commits');
});
