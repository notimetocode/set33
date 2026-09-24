<?php

namespace App\Jobs;

use App\Actions\Google\SyncSiteGoogleMetrics;
use App\Enums\GoogleConnectionStatus;
use App\Models\SiteGoogleIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class BackfillSiteGoogleMetricsJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 300;

    public function __construct(
        public int $integrationId,
    ) {}

    public function handle(SyncSiteGoogleMetrics $sync): void
    {
        $integration = SiteGoogleIntegration::query()
            ->with('googleConnection')
            ->find($this->integrationId);

        if ($integration === null || ! $integration->isConfigured()) {
            return;
        }

        if ($integration->googleConnection?->status === GoogleConnectionStatus::NeedsReauth) {
            return;
        }

        $sync->backfill($integration);
    }

    public function failed(?Throwable $exception): void
    {
        $integration = SiteGoogleIntegration::query()->find($this->integrationId);

        if ($integration === null) {
            return;
        }

        $integration->forceFill([
            'last_error' => mb_substr($exception?->getMessage() ?? 'Ошибка первичной загрузки', 0, 2000),
        ])->save();
    }
}
