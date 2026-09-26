<?php

namespace App\Http\Controllers\App;

use App\Actions\PageSpeed\SyncSitePageSpeedMetrics;
use App\Actions\PageSpeed\UpsertSitePageSpeedIntegration;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\PageSpeed\SyncSitePageSpeedIntegrationRequest;
use App\Http\Requests\App\PageSpeed\UpdateSitePageSpeedIntegrationRequest;
use App\Http\Resources\App\SitePageSpeedIntegrationResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class SitePageSpeedIntegrationController extends Controller
{
    public function show(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $integration = $site->pagespeedIntegration;
        $integration?->load('googleConnection');

        return response()->json([
            'data' => $integration
                ? new SitePageSpeedIntegrationResource($integration)
                : null,
        ]);
    }

    public function update(
        UpdateSitePageSpeedIntegrationRequest $request,
        Site $site,
        UpsertSitePageSpeedIntegration $upsert,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        try {
            $integration = $upsert->handle($request->user(), $site, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => new SitePageSpeedIntegrationResource($integration),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.update', $site);

        $site->pagespeedIntegration()?->delete();

        return response()->json(null, 204);
    }

    public function sync(
        SyncSitePageSpeedIntegrationRequest $request,
        Site $site,
        SyncSitePageSpeedMetrics $sync,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        $integration = $site->pagespeedIntegration;

        if ($integration === null || ! $integration->isConfigured()) {
            return response()->json([
                'message' => 'Сначала подключите PageSpeed Insights к сайту.',
            ], 422);
        }

        try {
            $integration = $sync->handle($integration, $request->metrics());
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось загрузить данные PageSpeed / CrUX.',
                'data' => new SitePageSpeedIntegrationResource($integration->refresh()->load('googleConnection')),
            ], 422);
        }

        return response()->json([
            'data' => new SitePageSpeedIntegrationResource($integration),
        ]);
    }
}
