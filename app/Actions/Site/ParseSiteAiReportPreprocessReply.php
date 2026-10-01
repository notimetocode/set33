<?php

namespace App\Actions\Site;

class ParseSiteAiReportPreprocessReply
{
    public const TYPE_GITHUB_COMMIT_FILES = 'github_commit_files';

    public const TYPE_GITHUB_COMMIT_FILES_COMPLETE = 'github_commit_files_complete';

    public const TYPE_GSC_URL_INSPECTION = 'gsc_url_inspection';

    public const TYPE_GSC_DIMENSIONS = 'gsc_dimensions';

    public const TYPE_GSC_SEARCH_APPEARANCES = 'gsc_search_appearances';

    public const TYPE_GSC_SITEMAPS = 'gsc_sitemaps';

    public const TYPE_GA_OPTIONAL_METRICS = 'ga_optional_metrics';

    public const TYPE_GA_PAGE_BREAKDOWN = 'ga_page_breakdown';

    public const TYPE_PAGESPEED_LAB_URL = 'pagespeed_lab_url';

    public const TYPE_PAGESPEED_LAB_DETAILS = 'pagespeed_lab_details';

    public const TYPE_CRUX_DETAILS = 'crux_details';

    public const TYPE_WEBSITE_HOMEPAGE_REFRESH = 'website_homepage_refresh';

    public const TYPE_WEBSITE_PAGE_SNAPSHOT = 'website_page_snapshot';

    public const ALLOWED_TYPES = [
        self::TYPE_GITHUB_COMMIT_FILES,
        self::TYPE_GITHUB_COMMIT_FILES_COMPLETE,
        self::TYPE_GSC_URL_INSPECTION,
        self::TYPE_GSC_DIMENSIONS,
        self::TYPE_GSC_SEARCH_APPEARANCES,
        self::TYPE_GSC_SITEMAPS,
        self::TYPE_GA_OPTIONAL_METRICS,
        self::TYPE_GA_PAGE_BREAKDOWN,
        self::TYPE_PAGESPEED_LAB_URL,
        self::TYPE_PAGESPEED_LAB_DETAILS,
        self::TYPE_CRUX_DETAILS,
        self::TYPE_WEBSITE_HOMEPAGE_REFRESH,
        self::TYPE_WEBSITE_PAGE_SNAPSHOT,
    ];

    private const PERIOD_ONLY_TYPES = [
        self::TYPE_GSC_DIMENSIONS,
        self::TYPE_GSC_SEARCH_APPEARANCES,
        self::TYPE_GSC_SITEMAPS,
        self::TYPE_GA_OPTIONAL_METRICS,
        self::TYPE_GA_PAGE_BREAKDOWN,
        self::TYPE_WEBSITE_HOMEPAGE_REFRESH,
    ];

    private const URL_TYPES = [
        self::TYPE_GSC_URL_INSPECTION,
        self::TYPE_PAGESPEED_LAB_URL,
        self::TYPE_WEBSITE_PAGE_SNAPSHOT,
    ];

    private const COMMIT_TYPES = [
        self::TYPE_GITHUB_COMMIT_FILES,
        self::TYPE_GITHUB_COMMIT_FILES_COMPLETE,
    ];

    private const SNAPSHOT_TYPES = [
        self::TYPE_PAGESPEED_LAB_DETAILS,
        self::TYPE_CRUX_DETAILS,
    ];

    private const MAX_ITEMS = 40;

    private const MAX_REASON_CHARS = 500;

    /**
     * @return array{
     *     ok: bool,
     *     items: list<array<string, mixed>>,
     *     message: string|null
     * }
     */
    public function handle(string $rawReply): array
    {
        $decoded = $this->decodeJson($rawReply);

        if ($decoded === null) {
            return [
                'ok' => false,
                'items' => [],
                'message' => 'Не удалось разобрать ответ AI: ожидался JSON со списком items.',
            ];
        }

        $rawItems = $decoded['items'] ?? null;

        if (! is_array($rawItems) || ! array_is_list($rawItems)) {
            return [
                'ok' => false,
                'items' => [],
                'message' => 'Не удалось разобрать ответ AI: поле items должно быть массивом.',
            ];
        }

        $items = [];

        foreach ($rawItems as $rawItem) {
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }

            if (! is_array($rawItem)) {
                continue;
            }

            $item = $this->normalizeItem($rawItem);

            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return [
            'ok' => true,
            'items' => $items,
            'message' => null,
        ];
    }

    public static function isPeriodOnlyType(string $type): bool
    {
        return in_array($type, self::PERIOD_ONLY_TYPES, true);
    }

    public static function isUrlType(string $type): bool
    {
        return in_array($type, self::URL_TYPES, true);
    }

    public static function isCommitType(string $type): bool
    {
        return in_array($type, self::COMMIT_TYPES, true);
    }

    public static function isSnapshotType(string $type): bool
    {
        return in_array($type, self::SNAPSHOT_TYPES, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $rawReply): ?array
    {
        $trimmed = trim($rawReply);

        if ($trimmed === '') {
            return null;
        }

        $candidates = [$trimmed];

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $trimmed, $matches) === 1) {
            $candidates[] = trim($matches[1]);
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($trimmed, $start, $end - $start + 1);
        }

        foreach (array_unique($candidates) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $item): ?array
    {
        $type = $item['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        $reason = $this->normalizeReason($item['reason'] ?? '');

        if (self::isCommitType($type)) {
            return $this->normalizeCommitItem($type, $item, $reason);
        }

        if (self::isUrlType($type)) {
            return $this->normalizeUrlItem($type, $item, $reason);
        }

        if (self::isSnapshotType($type)) {
            return $this->normalizeSnapshotItem($type, $item, $reason);
        }

        if (self::isPeriodOnlyType($type)) {
            return [
                'type' => $type,
                'reason' => $reason,
            ];
        }

        return null;
    }

    /**
     * @param  array<mixed>  $item
     * @return array{type: string, commit_id: int, sha: string, reason: string}|null
     */
    private function normalizeCommitItem(string $type, array $item, string $reason): ?array
    {
        $commitId = $item['commit_id'] ?? null;
        $sha = $item['sha'] ?? null;

        if (! is_int($commitId) && ! (is_string($commitId) && ctype_digit($commitId))) {
            return null;
        }

        $commitId = (int) $commitId;

        if ($commitId <= 0 || ! is_string($sha)) {
            return null;
        }

        $sha = strtolower(trim($sha));

        if ($sha === '' || ! preg_match('/^[a-f0-9]{7,40}$/', $sha)) {
            return null;
        }

        return [
            'type' => $type,
            'commit_id' => $commitId,
            'sha' => $sha,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<mixed>  $item
     * @return array{type: string, url: string, reason: string}|null
     */
    private function normalizeUrlItem(string $type, array $item, string $reason): ?array
    {
        $url = $item['url'] ?? null;

        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        return [
            'type' => $type,
            'url' => mb_substr($url, 0, 768),
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<mixed>  $item
     * @return array{type: string, snapshot_id: int, reason: string}|null
     */
    private function normalizeSnapshotItem(string $type, array $item, string $reason): ?array
    {
        $snapshotId = $item['snapshot_id'] ?? null;

        if (! is_int($snapshotId) && ! (is_string($snapshotId) && ctype_digit($snapshotId))) {
            return null;
        }

        $snapshotId = (int) $snapshotId;

        if ($snapshotId <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'snapshot_id' => $snapshotId,
            'reason' => $reason,
        ];
    }

    private function normalizeReason(mixed $reason): string
    {
        if (! is_string($reason)) {
            return '';
        }

        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? $reason);

        if (mb_strlen($reason) > self::MAX_REASON_CHARS) {
            return mb_substr($reason, 0, self::MAX_REASON_CHARS);
        }

        return $reason;
    }
}
