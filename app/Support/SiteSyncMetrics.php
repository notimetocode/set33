<?php

namespace App\Support;

final class SiteSyncMetrics
{
    public const ANALYTICS_DEFAULT = [
        'sessions',
        'total_users',
        'new_users',
        'screen_page_views',
        'organic_sessions',
        'organic_total_users',
        'organic_new_users',
    ];

    public const ANALYTICS_OPTIONAL = [
        'engaged_sessions',
        'engagement_rate',
        'bounce_rate',
        'average_session_duration',
        'event_count',
        'organic_engaged_sessions',
    ];

    public const ANALYTICS = [
        ...self::ANALYTICS_DEFAULT,
        ...self::ANALYTICS_OPTIONAL,
    ];

    public const SEARCH_CONSOLE_DAILY = [
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    public const SEARCH_CONSOLE_DIMENSIONS_DEFAULT = [
        'queries',
        'pages',
        'devices',
        'countries',
    ];

    public const SEARCH_CONSOLE_DIMENSIONS_OPTIONAL = [
        'search_appearances',
    ];

    public const SEARCH_CONSOLE_TOOLS_OPTIONAL = [
        'sitemaps',
        'url_inspections',
    ];

    public const SEARCH_CONSOLE_DIMENSIONS = [
        ...self::SEARCH_CONSOLE_DIMENSIONS_DEFAULT,
        ...self::SEARCH_CONSOLE_DIMENSIONS_OPTIONAL,
    ];

    public const SEARCH_CONSOLE_LIMIT_KEYS = [
        'queries',
        'pages',
        'url_inspections',
    ];

    public const GITHUB = [
        'commits',
    ];

    public const PAGESPEED_DEFAULT = [
        'psi_lab',
        'crux_origin',
    ];

    public const PAGESPEED_OPTIONAL = [
        'crux_url',
    ];

    public const PAGESPEED = [
        ...self::PAGESPEED_DEFAULT,
        ...self::PAGESPEED_OPTIONAL,
    ];

    /**
     * Integer analytics fields (as opposed to rates/durations).
     *
     * @var list<string>
     */
    public const ANALYTICS_INTEGER_FIELDS = [
        'sessions',
        'total_users',
        'new_users',
        'screen_page_views',
        'organic_sessions',
        'organic_total_users',
        'organic_new_users',
        'engaged_sessions',
        'event_count',
        'organic_engaged_sessions',
    ];

    /**
     * @return list<string>
     */
    public static function defaultGoogle(): array
    {
        return [
            ...self::ANALYTICS_DEFAULT,
            ...self::SEARCH_CONSOLE_DAILY,
            ...self::SEARCH_CONSOLE_DIMENSIONS_DEFAULT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function google(): array
    {
        return [
            ...self::ANALYTICS,
            ...self::SEARCH_CONSOLE_DAILY,
            ...self::SEARCH_CONSOLE_DIMENSIONS,
            ...self::SEARCH_CONSOLE_TOOLS_OPTIONAL,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ...self::google(),
            ...self::GITHUB,
            ...self::PAGESPEED,
        ];
    }

    /**
     * @param  list<string>|null  $metrics
     * @return list<string>
     */
    public static function resolvePageSpeed(?array $metrics): array
    {
        if ($metrics === null) {
            return self::PAGESPEED_DEFAULT;
        }

        return array_values(array_intersect($metrics, self::PAGESPEED));
    }

    /**
     * @param  list<string>|null  $metrics
     * @return list<string>
     */
    public static function resolveGoogle(?array $metrics): array
    {
        if ($metrics === null) {
            return self::defaultGoogle();
        }

        return array_values(array_intersect($metrics, self::google()));
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $group
     */
    public static function selectsAny(array $selected, array $group): bool
    {
        return array_intersect($selected, $group) !== [];
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $group
     * @return list<string>
     */
    public static function selectedIn(array $selected, array $group): array
    {
        return array_values(array_intersect($selected, $group));
    }

    public static function isAnalyticsInteger(string $metric): bool
    {
        return in_array($metric, self::ANALYTICS_INTEGER_FIELDS, true);
    }

    /**
     * @param  array<string, mixed>  $limits
     * @return array{queries: int, pages: int, url_inspections: int}
     */
    public static function resolveDimensionLimits(array $limits = []): array
    {
        return [
            'queries' => self::clampLimit(
                $limits['queries'] ?? null,
                (int) config('services.google.gsc_top_queries', 50),
            ),
            'pages' => self::clampLimit(
                $limits['pages'] ?? null,
                (int) config('services.google.gsc_top_pages', 20),
            ),
            'url_inspections' => self::clampLimit(
                $limits['url_inspections'] ?? null,
                (int) config('services.google.gsc_url_inspections', 10),
                max: 50,
            ),
        ];
    }

    private static function clampLimit(mixed $value, int $fallback, int $max = 1000): int
    {
        $limit = is_numeric($value) ? (int) $value : $fallback;

        return max(1, min($max, $limit));
    }
}
