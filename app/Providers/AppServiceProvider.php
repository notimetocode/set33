<?php

namespace App\Providers;

use App\Policies\Admin\DashboardPolicy as AdminDashboardPolicy;
use App\Policies\Admin\UserPolicy as AdminUserPolicy;
use App\Policies\App\ProfilePolicy as AppProfilePolicy;
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

        Gate::define('app.profile.view', [AppProfilePolicy::class, 'view']);
        Gate::define('app.profile.update', [AppProfilePolicy::class, 'update']);

        Gate::define('admin.dashboard.view', [AdminDashboardPolicy::class, 'view']);

        Gate::define('admin.users.viewAny', [AdminUserPolicy::class, 'viewAny']);
        Gate::define('admin.users.view', [AdminUserPolicy::class, 'view']);
        Gate::define('admin.users.create', [AdminUserPolicy::class, 'create']);
        Gate::define('admin.users.update', [AdminUserPolicy::class, 'update']);
        Gate::define('admin.users.delete', [AdminUserPolicy::class, 'delete']);
    }
}
