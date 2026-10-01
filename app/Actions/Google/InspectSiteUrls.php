<?php

namespace App\Actions\Google;

use App\Models\Site;
use App\Models\SiteGoogleIntegration;
use App\Models\SiteUrlInspection;
use App\Services\Google\GoogleApiClient;
use RuntimeException;

class InspectSiteUrls
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    /**
     * @param  list<string>  $urls
     * @return list<SiteUrlInspection>
     */
    public function handle(Site $site, array $urls, ?string $periodFrom = null, ?string $periodTo = null): array
    {
        $integration = $this->requireIntegration($site);
        $connection = $integration->googleConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту Google.');
        }

        if (! filled($integration->gsc_site_url)) {
            throw new RuntimeException('Search Console не привязан к сайту.');
        }

        $normalized = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '' || in_array($url, $normalized, true)) {
                continue;
            }
            $normalized[] = mb_substr($url, 0, 500);
        }

        if ($normalized === []) {
            throw new RuntimeException('Не указаны URL для инспекции.');
        }

        $rows = $this->googleApiClient->inspectUrls(
            $connection,
            $integration->gsc_site_url,
            $normalized,
        );

        $saved = [];

        foreach ($rows as $row) {
            $saved[] = SiteUrlInspection::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'inspected_url' => $row['inspected_url'],
                ],
                [
                    'period_from' => $periodFrom,
                    'period_to' => $periodTo,
                    'verdict' => $row['verdict'],
                    'coverage_state' => $row['coverage_state'],
                    'indexing_state' => $row['indexing_state'],
                    'page_fetch_state' => $row['page_fetch_state'],
                    'robots_txt_state' => $row['robots_txt_state'],
                    'crawled_as' => $row['crawled_as'],
                    'last_crawl_time' => $row['last_crawl_time'],
                    'google_canonical' => $row['google_canonical'],
                    'user_canonical' => $row['user_canonical'],
                    'inspection_result_link' => $row['inspection_result_link'],
                    'referring_urls' => $row['referring_urls'],
                    'sitemaps' => $row['sitemaps'],
                    'inspected_at' => now(),
                ],
            );
        }

        return $saved;
    }

    private function requireIntegration(Site $site): SiteGoogleIntegration
    {
        $integration = $site->googleIntegration;

        if ($integration === null) {
            throw new RuntimeException('Google-интеграция не настроена.');
        }

        $integration->loadMissing('googleConnection');

        return $integration;
    }
}
