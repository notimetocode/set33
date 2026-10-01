<?php

namespace App\Actions\Google;

use App\Models\Site;
use App\Models\SiteAnalyticsPage;
use App\Services\Google\GoogleApiClient;
use Illuminate\Support\Carbon;
use RuntimeException;

class SyncSiteAnalyticsPageBreakdown
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    public function handle(Site $site, string $from, string $to, int $limit = 50): int
    {
        $integration = $site->googleIntegration;

        if ($integration === null) {
            throw new RuntimeException('Google-интеграция не настроена.');
        }

        $integration->loadMissing('googleConnection');
        $connection = $integration->googleConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        if (! filled($integration->ga4_property_id)) {
            throw new RuntimeException('Google Analytics не привязан к сайту.');
        }

        $rows = $this->googleApiClient->fetchAnalyticsPages(
            $connection,
            $integration->ga4_property_id,
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->startOfDay(),
            $limit,
        );

        SiteAnalyticsPage::query()
            ->where('site_id', $site->id)
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->delete();

        $rank = 1;

        foreach ($rows as $row) {
            SiteAnalyticsPage::query()->create([
                'site_id' => $site->id,
                'period_from' => $from,
                'period_to' => $to,
                'page_path' => $row['page_path'],
                'rank' => $rank,
                'sessions' => $row['sessions'],
                'screen_page_views' => $row['screen_page_views'],
                'total_users' => $row['total_users'],
            ]);
            $rank++;
        }

        return count($rows);
    }
}
