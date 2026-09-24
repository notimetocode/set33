<?php

use App\Jobs\DispatchDailyGoogleMetricsSyncJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DispatchDailyGoogleMetricsSyncJob)
    ->dailyAt('06:00')
    ->withoutOverlapping(120)
    ->name('google-metrics-daily-sync');
