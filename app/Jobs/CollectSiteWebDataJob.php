<?php

namespace App\Jobs;

use App\Actions\Site\CollectSiteWebData;
use App\Enums\SiteWebDataStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class CollectSiteWebDataJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $siteId,
    ) {}

    public function handle(CollectSiteWebData $collect): void
    {
        $site = Site::query()->find($this->siteId);

        if ($site === null) {
            return;
        }

        $collect->handle($site);
    }

    public function failed(?Throwable $exception): void
    {
        $site = Site::query()->find($this->siteId);

        if ($site === null) {
            return;
        }

        $site->forceFill([
            'web_data_status' => SiteWebDataStatus::Failed,
            'web_data_error' => Str::limit($exception?->getMessage() ?? 'Failed to collect site data', 2000),
            'web_data_fetched_at' => now(),
        ])->save();
    }
}
