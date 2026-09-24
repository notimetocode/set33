<?php

namespace App\Providers;

use App\Policies\Admin\DashboardPolicy as AdminDashboardPolicy;
use App\Policies\Admin\UserPolicy as AdminUserPolicy;
use App\Policies\App\AiServicePolicy as AppAiServicePolicy;
use App\Policies\App\GithubConnectionPolicy as AppGithubConnectionPolicy;
use App\Policies\App\GoogleConnectionPolicy as AppGoogleConnectionPolicy;
use App\Policies\App\ProfilePolicy as AppProfilePolicy;
use App\Policies\App\SitePolicy as AppSitePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });

        RateLimiter::for('ai-service-models', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('ai-service-check', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('ai-service-generate', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('google-oauth-start', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('google-metrics-sync', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('github-oauth-start', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('github-api', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('github-commits-sync', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        Gate::define('app.profile.view', [AppProfilePolicy::class, 'view']);
        Gate::define('app.profile.update', [AppProfilePolicy::class, 'update']);

        Gate::define('app.ai-services.viewAny', [AppAiServicePolicy::class, 'viewAny']);
        Gate::define('app.ai-services.view', [AppAiServicePolicy::class, 'view']);
        Gate::define('app.ai-services.create', [AppAiServicePolicy::class, 'create']);
        Gate::define('app.ai-services.update', [AppAiServicePolicy::class, 'update']);
        Gate::define('app.ai-services.delete', [AppAiServicePolicy::class, 'delete']);
        Gate::define('app.ai-services.check', [AppAiServicePolicy::class, 'check']);
        Gate::define('app.ai-services.generate', [AppAiServicePolicy::class, 'generate']);

        Gate::define('app.sites.viewAny', [AppSitePolicy::class, 'viewAny']);
        Gate::define('app.sites.view', [AppSitePolicy::class, 'view']);
        Gate::define('app.sites.create', [AppSitePolicy::class, 'create']);
        Gate::define('app.sites.update', [AppSitePolicy::class, 'update']);
        Gate::define('app.sites.delete', [AppSitePolicy::class, 'delete']);

        Gate::define('app.google.view', [AppGoogleConnectionPolicy::class, 'view']);
        Gate::define('app.google.connect', [AppGoogleConnectionPolicy::class, 'connect']);
        Gate::define('app.google.disconnect', [AppGoogleConnectionPolicy::class, 'disconnect']);

        Gate::define('app.github.view', [AppGithubConnectionPolicy::class, 'view']);
        Gate::define('app.github.connect', [AppGithubConnectionPolicy::class, 'connect']);
        Gate::define('app.github.disconnect', [AppGithubConnectionPolicy::class, 'disconnect']);

        Gate::define('admin.dashboard.view', [AdminDashboardPolicy::class, 'view']);

        Gate::define('admin.users.viewAny', [AdminUserPolicy::class, 'viewAny']);
        Gate::define('admin.users.view', [AdminUserPolicy::class, 'view']);
        Gate::define('admin.users.create', [AdminUserPolicy::class, 'create']);
        Gate::define('admin.users.update', [AdminUserPolicy::class, 'update']);
        Gate::define('admin.users.delete', [AdminUserPolicy::class, 'delete']);
    }
}
