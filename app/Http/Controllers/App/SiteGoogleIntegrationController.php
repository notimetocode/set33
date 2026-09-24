<?php

namespace App\Http\Controllers\App;

use App\Actions\Google\SyncSiteGoogleMetrics;
use App\Actions\Google\UpsertSiteGoogleIntegration;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Google\SyncSiteGoogleIntegrationRequest;
use App\Http\Requests\App\Google\UpdateSiteGoogleIntegrationRequest;
use App\Http\Resources\App\SiteGoogleIntegrationResource;
use App\Jobs\BackfillSiteGoogleMetricsJob;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class SiteGoogleIntegrationController extends Controller
{
    public function show(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $integration = $site->googleIntegration;
        $integration?->load('googleConnection');

        return response()->json([
            'data' => $integration
                ? new SiteGoogleIntegrationResource($integration)
                : null,
        ]);
    }

    public function update(
        UpdateSiteGoogleIntegrationRequest $request,
        Site $site,
        UpsertSiteGoogleIntegration $upsert,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        try {
            $integration = $upsert->handle($request->user(), $site, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        BackfillSiteGoogleMetricsJob::dispatch($integration->id);

        return response()->json([
            'data' => new SiteGoogleIntegrationResource($integration),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.update', $site);

        $site->googleIntegration()?->delete();

        return response()->json(null, 204);
    }

    public function sync(
        SyncSiteGoogleIntegrationRequest $request,
        Site $site,
        SyncSiteGoogleMetrics $sync,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        $integration = $site->googleIntegration;

        if ($integration === null || ! $integration->isConfigured()) {
            return response()->json([
                'message' => 'Сначала настройте GA4 и/или Search Console для сайта.',
            ], 422);
        }

        try {
            $integration = $sync->handle(
                $integration,
                $request->date('from')->startOfDay(),
                $request->date('to')->startOfDay(),
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось синхронизировать метрики.',
                'data' => new SiteGoogleIntegrationResource($integration->refresh()->load('googleConnection')),
            ], 422);
        }

        return response()->json([
            'data' => new SiteGoogleIntegrationResource($integration),
        ]);
    }
}
