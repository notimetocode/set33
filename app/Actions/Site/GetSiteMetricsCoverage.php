<?php

namespace App\Actions\Site;

use App\Models\Site;
use Illuminate\Support\Carbon;

class GetSiteMetricsCoverage
{
    /**
     * @return array{
     *     analytics: array{from: string, to: string}|null,
     *     search_console_daily: array{from: string, to: string}|null,
     *     search_console_dimensions: array{from: string, to: string}|null,
     *     github_commits: array{from: string, to: string}|null,
     *     pagespeed_lab: array{from: string, to: string}|null,
     *     crux: array{from: string, to: string}|null
     * }
     */
    public function handle(Site $site): array
    {
        return [
            'analytics' => $this->normalizeRange(
                $site->analyticsDaily()->min('date'),
                $site->analyticsDaily()->max('date'),
            ),
            'search_console_daily' => $this->normalizeRange(
                $site->searchConsoleDaily()->min('date'),
                $site->searchConsoleDaily()->max('date'),
            ),
            'search_console_dimensions' => $this->searchConsoleDimensionsRange($site),
            'github_commits' => $this->normalizeRange(
                $site->githubCommits()->min('author_date'),
                $site->githubCommits()->max('author_date'),
            ),
            'pagespeed_lab' => $this->normalizeRange(
                $site->pagespeedLabSnapshots()->min('fetched_at'),
                $site->pagespeedLabSnapshots()->max('fetched_at'),
            ),
            'crux' => $this->cruxRange($site),
        ];
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function searchConsoleDimensionsRange(Site $site): ?array
    {
        $dimensionFrom = $site->searchConsoleDimensions()->min('period_from');
        $dimensionTo = $site->searchConsoleDimensions()->max('period_to');
        $inspectionFrom = $site->urlInspections()->min('period_from');
        $inspectionTo = $site->urlInspections()->max('period_to');

        $fromCandidates = array_values(array_filter(
            [$dimensionFrom, $inspectionFrom],
            fn (mixed $value): bool => filled($value),
        ));
        $toCandidates = array_values(array_filter(
            [$dimensionTo, $inspectionTo],
            fn (mixed $value): bool => filled($value),
        ));

        if ($fromCandidates === [] || $toCandidates === []) {
            return null;
        }

        return $this->normalizeRange(min($fromCandidates), max($toCandidates));
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function cruxRange(Site $site): ?array
    {
        $from = $site->cruxSnapshots()->min('collection_period_start');
        $to = $site->cruxSnapshots()->max('collection_period_end');

        if (filled($from) && filled($to)) {
            return $this->normalizeRange($from, $to);
        }

        return $this->normalizeRange(
            $site->cruxSnapshots()->min('fetched_at'),
            $site->cruxSnapshots()->max('fetched_at'),
        );
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function normalizeRange(mixed $from, mixed $to): ?array
    {
        if (! filled($from) || ! filled($to)) {
            return null;
        }

        return [
            'from' => Carbon::parse($from)->toDateString(),
            'to' => Carbon::parse($to)->toDateString(),
        ];
    }
}
