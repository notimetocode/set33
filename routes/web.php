<?php

use App\Http\Controllers\Admin\SpaController as AdminSpaController;
use App\Http\Controllers\App\GithubOAuthCallbackController;
use App\Http\Controllers\App\GoogleOAuthCallbackController;
use App\Http\Controllers\App\SpaController as AppSpaController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\PrivacyPolicyController;
use App\Http\Controllers\PublicSite\SharedAiReportController;
use App\Http\Controllers\PublicSite\TermsOfServiceController;
use App\Support\Localization;
use Illuminate\Support\Facades\Route;

$registerPublicRoutes = function (bool $named): void {
    $home = Route::get('/', HomeController::class);
    $privacy = Route::get('/privacy', PrivacyPolicyController::class);
    $terms = Route::get('/terms', TermsOfServiceController::class);
    $reportShow = Route::get('/r/{token}', [SharedAiReportController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{20,64}');
    $reportUnlock = Route::post('/r/{token}/unlock', [SharedAiReportController::class, 'unlock'])
        ->where('token', '[A-Za-z0-9]{20,64}')
        ->middleware('throttle:ai-report-unlock');

    if ($named) {
        $home->name('public.home');
        $privacy->name('public.privacy');
        $terms->name('public.terms');
        $reportShow->name('public.ai-reports.show');
        $reportUnlock->name('public.ai-reports.unlock');
    }
};

Route::prefix('{locale}')
    ->where(['locale' => Localization::nonDefaultLocalesPattern()])
    ->middleware('locale')
    ->group(fn () => $registerPublicRoutes(false));

Route::middleware('locale')->group(fn () => $registerPublicRoutes(true));

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
