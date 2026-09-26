<?php

namespace App\Http\Controllers\App;

use App\Enums\SearchConsoleDimension;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\SiteMetricsRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SiteMetricsController extends Controller
{
    public function analytics(SiteMetricsRequest $request, Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $from = $request->date('from')->toDateString();
        $to = $request->date('to')->toDateString();

        $rows = $site->analyticsDaily()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'sessions' => $row->sessions,
                'total_users' => $row->total_users,
                'new_users' => $row->new_users,
                'screen_page_views' => $row->screen_page_views,
                'organic_sessions' => $row->organic_sessions,
                'organic_total_users' => $row->organic_total_users,
                'organic_new_users' => $row->organic_new_users,
                'engaged_sessions' => $row->engaged_sessions,
                'engagement_rate' => $row->engagement_rate,
                'bounce_rate' => $row->bounce_rate,
                'average_session_duration' => $row->average_session_duration,
                'event_count' => $row->event_count,
                'organic_engaged_sessions' => $row->organic_engaged_sessions,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function searchConsole(SiteMetricsRequest $request, Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $from = $request->date('from')->toDateString();
        $to = $request->date('to')->toDateString();

        $daily = $site->searchConsoleDaily()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'clicks' => $row->clicks,
                'impressions' => $row->impressions,
                'ctr' => $row->ctr,
                'position' => $row->position,
            ])
            ->values()
            ->all();

        $dimensionRows = $site->searchConsoleDimensions()
            ->where('period_from', $from)
            ->where('period_to', $to)
            ->orderBy('dimension')
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $mapDimension = static fn ($row) => [
            'value' => $row->value,
            'rank' => $row->rank,
            'clicks' => $row->clicks,
            'impressions' => $row->impressions,
            'ctr' => $row->ctr,
            'position' => $row->position,
        ];

        return response()->json([
            'data' => [
                'daily' => $daily,
                'queries' => $dimensionRows
                    ->where('dimension', SearchConsoleDimension::Query)
                    ->values()
                    ->map($mapDimension)
                    ->all(),
                'pages' => $dimensionRows
                    ->where('dimension', SearchConsoleDimension::Page)
                    ->values()
                    ->map($mapDimension)
                    ->all(),
                'devices' => $dimensionRows
                    ->where('dimension', SearchConsoleDimension::Device)
                    ->values()
                    ->map($mapDimension)
                    ->all(),
                'countries' => $dimensionRows
                    ->where('dimension', SearchConsoleDimension::Country)
                    ->values()
                    ->map($mapDimension)
                    ->all(),
                'search_appearances' => $dimensionRows
                    ->where('dimension', SearchConsoleDimension::SearchAppearance)
                    ->values()
                    ->map($mapDimension)
                    ->all(),
                'sitemaps' => $site->searchConsoleSitemaps()
                    ->orderByDesc('errors')
                    ->orderByDesc('warnings')
                    ->orderBy('path')
                    ->get()
                    ->map(fn ($row) => [
                        'path' => $row->path,
                        'type' => $row->type,
                        'is_pending' => $row->is_pending,
                        'is_sitemaps_index' => $row->is_sitemaps_index,
                        'last_downloaded_at' => $row->last_downloaded_at?->toIso8601String(),
                        'last_submitted_at' => $row->last_submitted_at?->toIso8601String(),
                        'errors' => $row->errors,
                        'warnings' => $row->warnings,
                        'contents' => $row->contents ?? [],
                    ])
                    ->all(),
                'url_inspections' => $site->urlInspections()
                    ->orderByDesc('inspected_at')
                    ->orderBy('inspected_url')
                    ->get()
                    ->map(fn ($row) => [
                        'inspected_url' => $row->inspected_url,
                        'period_from' => $row->period_from?->toDateString(),
                        'period_to' => $row->period_to?->toDateString(),
                        'verdict' => $row->verdict,
                        'coverage_state' => $row->coverage_state,
                        'indexing_state' => $row->indexing_state,
                        'page_fetch_state' => $row->page_fetch_state,
                        'robots_txt_state' => $row->robots_txt_state,
                        'crawled_as' => $row->crawled_as,
                        'last_crawl_time' => $row->last_crawl_time?->toIso8601String(),
                        'google_canonical' => $row->google_canonical,
                        'user_canonical' => $row->user_canonical,
                        'inspection_result_link' => $row->inspection_result_link,
                        'referring_urls' => $row->referring_urls ?? [],
                        'sitemaps' => $row->sitemaps ?? [],
                        'inspected_at' => $row->inspected_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
        ]);
    }

    public function githubCommits(SiteMetricsRequest $request, Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $from = $request->date('from')->startOfDay();
        $to = $request->date('to')->endOfDay();

        $rows = $site->githubCommits()
            ->whereBetween('author_date', [$from, $to])
            ->orderByDesc('author_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'sha' => $row->sha,
                'short_sha' => substr($row->sha, 0, 7),
                'message' => $row->message,
                'html_url' => $row->html_url,
                'author_name' => $row->author_name,
                'author_email' => $row->author_email,
                'author_date' => $row->author_date?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function pagespeed(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $lab = $site->pagespeedLabSnapshots()
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'url' => $row->url,
                'strategy' => $row->strategy,
                'fetched_at' => $row->fetched_at?->toIso8601String(),
                'performance_score' => $row->performance_score,
                'lcp_ms' => $row->lcp_ms,
                'inp_ms' => $row->inp_ms,
                'cls' => $row->cls,
                'fcp_ms' => $row->fcp_ms,
                'ttfb_ms' => $row->ttfb_ms,
                'tbt_ms' => $row->tbt_ms,
                'speed_index_ms' => $row->speed_index_ms,
            ])
            ->all();

        $crux = $site->cruxSnapshots()
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn ($row) => [
                'scope' => $row->scope,
                'url' => $row->url,
                'form_factor' => $row->form_factor,
                'collection_period_start' => $row->collection_period_start?->toDateString(),
                'collection_period_end' => $row->collection_period_end?->toDateString(),
                'lcp_p75_ms' => $row->lcp_p75_ms,
                'inp_p75_ms' => $row->inp_p75_ms,
                'cls_p75' => $row->cls_p75,
                'fcp_p75_ms' => $row->fcp_p75_ms,
                'ttfb_p75_ms' => $row->ttfb_p75_ms,
                'fetched_at' => $row->fetched_at?->toIso8601String(),
            ])
            ->all();

        return response()->json([
            'data' => [
                'lab' => $lab,
                'crux' => $crux,
            ],
        ]);
    }
}
