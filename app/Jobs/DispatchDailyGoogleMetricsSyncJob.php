<?php

namespace App\Jobs;

use App\Enums\GoogleConnectionStatus;
use App\Enums\SiteGoogleIntegrationStatus;
use App\Models\SiteGoogleIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDailyGoogleMetricsSyncJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SiteGoogleIntegration::query()
            ->whereIn('status', [
                SiteGoogleIntegrationStatus::Active->value,
                SiteGoogleIntegrationStatus::Error->value,
            ])
            ->where(function ($query): void {
                $query->whereNotNull('ga4_property_id')
                    ->orWhereNotNull('gsc_site_url');
            })
            ->whereHas('googleConnection', function ($query): void {
                $query->where('status', GoogleConnectionStatus::Active->value);
            })
            ->orderBy('id')
            ->chunkById(100, function ($integrations): void {
                foreach ($integrations as $integration) {
                    SyncSiteGoogleMetricsJob::dispatch($integration->id);
                }
            });
    }
}
