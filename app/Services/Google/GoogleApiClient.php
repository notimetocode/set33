<?php

namespace App\Services\Google;

use App\Enums\GoogleConnectionStatus;
use App\Enums\SearchConsoleDimension;
use App\Models\GoogleConnection;
use App\Support\SiteSyncMetrics;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\FilterExpression;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\StringFilter;
use Google\Service\GoogleAnalyticsAdmin;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\InspectUrlIndexRequest;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Carbon;
use RuntimeException;

class GoogleApiClient
{
    public function __construct(
        private readonly RefreshGoogleAccessToken $refreshGoogleAccessToken,
    ) {}

    /**
     * @return list<array{id: string, display_name: string, account: string}>
     */
    public function listGa4Properties(GoogleConnection $connection): array
    {
        $client = $this->authenticatedClient($connection);
        $admin = new GoogleAnalyticsAdmin($client);
        $properties = [];
        $pageToken = null;

        do {
            $response = $admin->accountSummaries->listAccountSummaries([
                'pageSize' => 200,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getAccountSummaries() ?? [] as $accountSummary) {
                $accountName = (string) ($accountSummary->getDisplayName() ?: $accountSummary->getAccount());

                foreach ($accountSummary->getPropertySummaries() ?? [] as $propertySummary) {
                    $property = (string) $propertySummary->getProperty();

                    if ($property === '') {
                        continue;
                    }

                    $properties[] = [
                        'id' => $property,
                        'display_name' => (string) ($propertySummary->getDisplayName() ?: $property),
                        'account' => $accountName,
                    ];
                }
            }

            $pageToken = $response->getNextPageToken();
        } while (filled($pageToken));

        return $properties;
    }

    /**
     * @return list<array{site_url: string, permission_level: string|null}>
     */
    public function listGscSites(GoogleConnection $connection): array
    {
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);
        $sites = [];

        foreach ($searchConsole->sites->listSites()->getSiteEntry() ?? [] as $entry) {
            $siteUrl = (string) $entry->getSiteUrl();

            if ($siteUrl === '') {
                continue;
            }

            $sites[] = [
                'site_url' => $siteUrl,
                'permission_level' => $entry->getPermissionLevel(),
            ];
        }

        return $sites;
    }

    /**
     * @param  list<string>|null  $selectedMetrics
     * @return list<array<string, int|float|string>>
     */
    public function fetchAnalyticsDaily(
        GoogleConnection $connection,
        string $propertyId,
        Carbon $startDate,
        Carbon $endDate,
        ?array $selectedMetrics = null,
    ): array {
        $selectedMetrics ??= SiteSyncMetrics::ANALYTICS_DEFAULT;

        $totalsMetricMap = [
            'sessions' => 'sessions',
            'total_users' => 'totalUsers',
            'new_users' => 'newUsers',
            'screen_page_views' => 'screenPageViews',
            'engaged_sessions' => 'engagedSessions',
            'engagement_rate' => 'engagementRate',
            'bounce_rate' => 'bounceRate',
            'average_session_duration' => 'averageSessionDuration',
            'event_count' => 'eventCount',
        ];
        $organicMetricMap = [
            'organic_sessions' => 'sessions',
            'organic_total_users' => 'totalUsers',
            'organic_new_users' => 'newUsers',
            'organic_engaged_sessions' => 'engagedSessions',
        ];

        $totalsApiMetrics = [];
        $totalsFieldOrder = [];

        foreach ($totalsMetricMap as $field => $apiName) {
            if (in_array($field, $selectedMetrics, true)) {
                $totalsApiMetrics[] = $apiName;
                $totalsFieldOrder[] = $field;
            }
        }

        $organicApiMetrics = [];
        $organicFieldOrder = [];

        foreach ($organicMetricMap as $field => $apiName) {
            if (in_array($field, $selectedMetrics, true)) {
                $organicApiMetrics[] = $apiName;
                $organicFieldOrder[] = $field;
            }
        }

        $client = $this->authenticatedClient($connection);
        $analytics = new AnalyticsData($client);

        $totalsByDate = $totalsApiMetrics === []
            ? []
            : $this->runAnalyticsDailyReport(
                $analytics,
                $propertyId,
                $startDate,
                $endDate,
                $totalsApiMetrics,
                $totalsFieldOrder,
            );

        $organicByDate = $organicApiMetrics === []
            ? []
            : $this->runAnalyticsDailyReport(
                $analytics,
                $propertyId,
                $startDate,
                $endDate,
                $organicApiMetrics,
                $organicFieldOrder,
                channelGroup: 'Organic Search',
            );

        $dates = array_values(array_unique(array_merge(
            array_keys($totalsByDate),
            array_keys($organicByDate),
        )));
        sort($dates);

        $empty = [
            'sessions' => 0,
            'total_users' => 0,
            'new_users' => 0,
            'screen_page_views' => 0,
            'organic_sessions' => 0,
            'organic_total_users' => 0,
            'organic_new_users' => 0,
            'engaged_sessions' => 0,
            'engagement_rate' => 0.0,
            'bounce_rate' => 0.0,
            'average_session_duration' => 0.0,
            'event_count' => 0,
            'organic_engaged_sessions' => 0,
        ];

        $rows = [];

        foreach ($dates as $date) {
            $totals = $totalsByDate[$date] ?? [];
            $organic = $organicByDate[$date] ?? [];
            $row = ['date' => $date, ...$empty];

            foreach ($totals as $field => $value) {
                $row[$field] = $value;
            }

            foreach ($organic as $field => $value) {
                $row[$field] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $apiMetricNames
     * @param  list<string>  $fieldOrder
     * @return array<string, array<string, int|float>>
     */
    private function runAnalyticsDailyReport(
        AnalyticsData $analytics,
        string $propertyId,
        Carbon $startDate,
        Carbon $endDate,
        array $apiMetricNames,
        array $fieldOrder,
        ?string $channelGroup = null,
    ): array {
        $metrics = array_map(
            fn (string $name): Metric => new Metric(['name' => $name]),
            $apiMetricNames,
        );

        $request = new RunReportRequest;
        $request->setDateRanges([
            new DateRange([
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ]),
        ]);
        $request->setDimensions([
            new Dimension(['name' => 'date']),
        ]);
        $request->setMetrics($metrics);

        if ($channelGroup !== null) {
            $stringFilter = new StringFilter;
            $stringFilter->setMatchType('EXACT');
            $stringFilter->setValue($channelGroup);

            $filter = new Filter;
            $filter->setFieldName('sessionDefaultChannelGroup');
            $filter->setStringFilter($stringFilter);

            $dimensionFilter = new FilterExpression;
            $dimensionFilter->setFilter($filter);

            $request->setDimensionFilter($dimensionFilter);
        }

        $response = $analytics->properties->runReport($propertyId, $request);
        $rows = [];

        foreach ($response->getRows() ?? [] as $row) {
            $dimensionValues = $row->getDimensionValues() ?? [];
            $metricValues = $row->getMetricValues() ?? [];
            $rawDate = (string) ($dimensionValues[0]?->getValue() ?? '');

            if (strlen($rawDate) !== 8) {
                continue;
            }

            $date = substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2);
            $payload = [];

            foreach ($fieldOrder as $index => $field) {
                $raw = (string) ($metricValues[$index]?->getValue() ?? '0');
                $payload[$field] = SiteSyncMetrics::isAnalyticsInteger($field)
                    ? (int) $raw
                    : (float) $raw;
            }

            $rows[$date] = $payload;
        }

        return $rows;
    }

    /**
     * @return list<array{date: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function fetchSearchConsoleDaily(
        GoogleConnection $connection,
        string $siteUrl,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);

        $request = new SearchAnalyticsQueryRequest;
        $request->setStartDate($startDate->toDateString());
        $request->setEndDate($endDate->toDateString());
        $request->setDimensions(['date']);
        $request->setRowLimit(25000);

        $response = $searchConsole->searchanalytics->query($siteUrl, $request);
        $rows = [];

        foreach ($response->getRows() ?? [] as $row) {
            $keys = $row->getKeys() ?? [];
            $date = (string) ($keys[0] ?? '');

            if ($date === '') {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'clicks' => (int) $row->getClicks(),
                'impressions' => (int) $row->getImpressions(),
                'ctr' => (float) $row->getCtr(),
                'position' => (float) $row->getPosition(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>|null  $dimensionKeys  keys: queries|pages|devices|countries|search_appearances
     * @param  array{queries?: int, pages?: int}  $limits
     * @return array{
     *     queries?: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     pages?: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     devices?: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     countries?: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     search_appearances?: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>
     * }
     */
    public function fetchSearchConsoleDimensions(
        GoogleConnection $connection,
        string $siteUrl,
        Carbon $startDate,
        Carbon $endDate,
        ?array $dimensionKeys = null,
        array $limits = [],
    ): array {
        $dimensionKeys ??= SiteSyncMetrics::SEARCH_CONSOLE_DIMENSIONS_DEFAULT;
        $resolvedLimits = SiteSyncMetrics::resolveDimensionLimits($limits);
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);

        $definitions = [
            'queries' => [
                'enum' => SearchConsoleDimension::Query,
                'limit' => $resolvedLimits['queries'],
            ],
            'pages' => [
                'enum' => SearchConsoleDimension::Page,
                'limit' => $resolvedLimits['pages'],
            ],
            'devices' => [
                'enum' => SearchConsoleDimension::Device,
                'limit' => 10,
            ],
            'countries' => [
                'enum' => SearchConsoleDimension::Country,
                'limit' => max(1, (int) config('services.google.gsc_top_countries', 10)),
            ],
            'search_appearances' => [
                'enum' => SearchConsoleDimension::SearchAppearance,
                'limit' => 25,
            ],
        ];

        $result = [];

        foreach ($dimensionKeys as $key) {
            if (! isset($definitions[$key])) {
                continue;
            }

            $result[$key] = $this->querySearchConsoleDimension(
                $searchConsole,
                $siteUrl,
                $startDate,
                $endDate,
                $definitions[$key]['enum'],
                $definitions[$key]['limit'],
            );
        }

        return $result;
    }

    /**
     * @return list<array{
     *     path: string,
     *     type: ?string,
     *     is_pending: bool,
     *     is_sitemaps_index: bool,
     *     last_downloaded_at: ?string,
     *     last_submitted_at: ?string,
     *     errors: int,
     *     warnings: int,
     *     contents: list<array{type: string, submitted: int}>
     * }>
     */
    public function fetchSitemaps(GoogleConnection $connection, string $siteUrl): array
    {
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);
        $response = $searchConsole->sitemaps->listSitemaps($siteUrl);
        $rows = [];

        foreach ($response->getSitemap() ?? [] as $sitemap) {
            $path = (string) ($sitemap->getPath() ?? '');

            if ($path === '') {
                continue;
            }

            $contents = [];

            foreach ($sitemap->getContents() ?? [] as $content) {
                $contents[] = [
                    'type' => (string) ($content->getType() ?? ''),
                    'submitted' => (int) ($content->getSubmitted() ?? 0),
                ];
            }

            $rows[] = [
                'path' => mb_substr($path, 0, 768),
                'type' => $sitemap->getType() !== null ? (string) $sitemap->getType() : null,
                'is_pending' => (bool) $sitemap->getIsPending(),
                'is_sitemaps_index' => (bool) $sitemap->getIsSitemapsIndex(),
                'last_downloaded_at' => $this->nullableRfc3339($sitemap->getLastDownloaded()),
                'last_submitted_at' => $this->nullableRfc3339($sitemap->getLastSubmitted()),
                'errors' => (int) ($sitemap->getErrors() ?? 0),
                'warnings' => (int) ($sitemap->getWarnings() ?? 0),
                'contents' => $contents,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{
     *     inspected_url: string,
     *     verdict: ?string,
     *     coverage_state: ?string,
     *     indexing_state: ?string,
     *     page_fetch_state: ?string,
     *     robots_txt_state: ?string,
     *     crawled_as: ?string,
     *     last_crawl_time: ?string,
     *     google_canonical: ?string,
     *     user_canonical: ?string,
     *     inspection_result_link: ?string,
     *     referring_urls: list<string>,
     *     sitemaps: list<string>
     * }>
     */
    public function inspectUrls(
        GoogleConnection $connection,
        string $siteUrl,
        array $urls,
    ): array {
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);
        $rows = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            $request = new InspectUrlIndexRequest;
            $request->setInspectionUrl($url);
            $request->setSiteUrl($siteUrl);
            $request->setLanguageCode('ru-RU');

            $response = $searchConsole->urlInspection_index->inspect($request);
            $result = $response->getInspectionResult();
            $indexStatus = $result?->getIndexStatusResult();

            $rows[] = [
                'inspected_url' => mb_substr($url, 0, 768),
                'verdict' => $indexStatus?->getVerdict(),
                'coverage_state' => $indexStatus?->getCoverageState(),
                'indexing_state' => $indexStatus?->getIndexingState(),
                'page_fetch_state' => $indexStatus?->getPageFetchState(),
                'robots_txt_state' => $indexStatus?->getRobotsTxtState(),
                'crawled_as' => $indexStatus?->getCrawledAs(),
                'last_crawl_time' => $this->nullableRfc3339($indexStatus?->getLastCrawlTime()),
                'google_canonical' => $this->nullableTruncated($indexStatus?->getGoogleCanonical()),
                'user_canonical' => $this->nullableTruncated($indexStatus?->getUserCanonical()),
                'inspection_result_link' => $this->nullableTruncated($result?->getInspectionResultLink()),
                'referring_urls' => array_values(array_filter(
                    array_map('strval', $indexStatus?->getReferringUrls() ?? []),
                )),
                'sitemaps' => array_values(array_filter(
                    array_map('strval', $indexStatus?->getSitemap() ?? []),
                )),
            ];
        }

        return $rows;
    }

    private function nullableRfc3339(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    private function nullableTruncated(?string $value, int $max = 768): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @return list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    private function querySearchConsoleDimension(
        SearchConsole $searchConsole,
        string $siteUrl,
        Carbon $startDate,
        Carbon $endDate,
        SearchConsoleDimension $dimension,
        int $rowLimit,
    ): array {
        $request = new SearchAnalyticsQueryRequest;
        $request->setStartDate($startDate->toDateString());
        $request->setEndDate($endDate->toDateString());
        $request->setDimensions([$dimension->apiDimension()]);
        $request->setRowLimit($rowLimit);

        $response = $searchConsole->searchanalytics->query($siteUrl, $request);
        $rows = [];

        foreach ($response->getRows() ?? [] as $row) {
            $keys = $row->getKeys() ?? [];
            $value = (string) ($keys[0] ?? '');

            if ($value === '') {
                continue;
            }

            $rows[] = [
                'value' => $value,
                'clicks' => (int) $row->getClicks(),
                'impressions' => (int) $row->getImpressions(),
                'ctr' => (float) $row->getCtr(),
                'position' => (float) $row->getPosition(),
            ];
        }

        return $rows;
    }

    public function revoke(GoogleConnection $connection): void
    {
        $client = $this->baseClient();

        if (filled($connection->access_token)) {
            $client->revokeToken($connection->access_token);
        }
    }

    private function authenticatedClient(GoogleConnection $connection): GoogleClient
    {
        if ($connection->status === GoogleConnectionStatus::NeedsReauth) {
            throw new RuntimeException('Требуется повторная авторизация Google.');
        }

        $connection = $this->refreshGoogleAccessToken->handle($connection);
        $client = $this->baseClient();
        $client->setAccessToken([
            'access_token' => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
            'expires_in' => max(0, (int) ($connection->expires_at?->getTimestamp() - now()->getTimestamp())),
            'created' => now()->getTimestamp(),
        ]);

        return $client;
    }

    private function baseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));

        return $client;
    }
}
