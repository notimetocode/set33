<?php

namespace App\Services\Google;

use App\Enums\GoogleConnectionStatus;
use App\Enums\SearchConsoleDimension;
use App\Models\GoogleConnection;
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
     * @return list<array{
     *     date: string,
     *     sessions: int,
     *     total_users: int,
     *     new_users: int,
     *     screen_page_views: int,
     *     organic_sessions: int,
     *     organic_total_users: int,
     *     organic_new_users: int
     * }>
     */
    public function fetchAnalyticsDaily(
        GoogleConnection $connection,
        string $propertyId,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $client = $this->authenticatedClient($connection);
        $analytics = new AnalyticsData($client);

        $totalsByDate = $this->runAnalyticsDailyReport(
            $analytics,
            $propertyId,
            $startDate,
            $endDate,
            includePageViews: true,
        );

        $organicByDate = $this->runAnalyticsDailyReport(
            $analytics,
            $propertyId,
            $startDate,
            $endDate,
            includePageViews: false,
            channelGroup: 'Organic Search',
        );

        $dates = array_values(array_unique(array_merge(
            array_keys($totalsByDate),
            array_keys($organicByDate),
        )));
        sort($dates);

        $rows = [];

        foreach ($dates as $date) {
            $totals = $totalsByDate[$date] ?? [
                'sessions' => 0,
                'total_users' => 0,
                'new_users' => 0,
                'screen_page_views' => 0,
            ];
            $organic = $organicByDate[$date] ?? [
                'sessions' => 0,
                'total_users' => 0,
                'new_users' => 0,
            ];

            $rows[] = [
                'date' => $date,
                'sessions' => $totals['sessions'],
                'total_users' => $totals['total_users'],
                'new_users' => $totals['new_users'],
                'screen_page_views' => $totals['screen_page_views'],
                'organic_sessions' => $organic['sessions'],
                'organic_total_users' => $organic['total_users'],
                'organic_new_users' => $organic['new_users'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{sessions: int, total_users: int, new_users: int, screen_page_views?: int}>
     */
    private function runAnalyticsDailyReport(
        AnalyticsData $analytics,
        string $propertyId,
        Carbon $startDate,
        Carbon $endDate,
        bool $includePageViews,
        ?string $channelGroup = null,
    ): array {
        $metrics = [
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'newUsers']),
        ];

        if ($includePageViews) {
            $metrics[] = new Metric(['name' => 'screenPageViews']);
        }

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
            $payload = [
                'sessions' => (int) ($metricValues[0]?->getValue() ?? 0),
                'total_users' => (int) ($metricValues[1]?->getValue() ?? 0),
                'new_users' => (int) ($metricValues[2]?->getValue() ?? 0),
            ];

            if ($includePageViews) {
                $payload['screen_page_views'] = (int) ($metricValues[3]?->getValue() ?? 0);
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
     * @return array{
     *     queries: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     pages: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     devices: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     countries: list<array{value: string, clicks: int, impressions: int, ctr: float, position: float}>
     * }
     */
    public function fetchSearchConsoleDimensions(
        GoogleConnection $connection,
        string $siteUrl,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $client = $this->authenticatedClient($connection);
        $searchConsole = new SearchConsole($client);

        return [
            'queries' => $this->querySearchConsoleDimension(
                $searchConsole,
                $siteUrl,
                $startDate,
                $endDate,
                SearchConsoleDimension::Query,
                max(1, (int) config('services.google.gsc_top_queries', 50)),
            ),
            'pages' => $this->querySearchConsoleDimension(
                $searchConsole,
                $siteUrl,
                $startDate,
                $endDate,
                SearchConsoleDimension::Page,
                max(1, (int) config('services.google.gsc_top_pages', 20)),
            ),
            'devices' => $this->querySearchConsoleDimension(
                $searchConsole,
                $siteUrl,
                $startDate,
                $endDate,
                SearchConsoleDimension::Device,
                10,
            ),
            'countries' => $this->querySearchConsoleDimension(
                $searchConsole,
                $siteUrl,
                $startDate,
                $endDate,
                SearchConsoleDimension::Country,
                max(1, (int) config('services.google.gsc_top_countries', 10)),
            ),
        ];
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
