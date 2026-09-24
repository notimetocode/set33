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
}
