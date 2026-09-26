<?php

namespace App\Http\Controllers\App;

use App\Actions\Github\SyncSiteGithubCommits;
use App\Actions\Github\UpsertSiteGithubIntegration;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Github\SyncSiteGithubIntegrationRequest;
use App\Http\Requests\App\Github\UpdateSiteGithubIntegrationRequest;
use App\Http\Resources\App\SiteGithubIntegrationResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class SiteGithubIntegrationController extends Controller
{
    public function show(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);

        $integration = $site->githubIntegration;
        $integration?->load('githubConnection');

        return response()->json([
            'data' => $integration
                ? new SiteGithubIntegrationResource($integration)
                : null,
        ]);
    }

    public function update(
        UpdateSiteGithubIntegrationRequest $request,
        Site $site,
        UpsertSiteGithubIntegration $upsert,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        try {
            $integration = $upsert->handle($request->user(), $site, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => new SiteGithubIntegrationResource($integration),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.update', $site);

        $site->githubIntegration()?->delete();

        return response()->json(null, 204);
    }

    public function sync(
        SyncSiteGithubIntegrationRequest $request,
        Site $site,
        SyncSiteGithubCommits $sync,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        $integration = $site->githubIntegration;

        if ($integration === null || ! $integration->isConfigured()) {
            return response()->json([
                'message' => 'Сначала привяжите репозиторий и ветку GitHub к сайту.',
            ], 422);
        }

        try {
            $integration = $sync->handle(
                $integration,
                $request->date('from')->startOfDay(),
                $request->date('to')->startOfDay(),
                replaceExisting: true,
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось синхронизировать коммиты GitHub.',
                'data' => new SiteGithubIntegrationResource($integration->refresh()->load('githubConnection')),
            ], 422);
        }

        return response()->json([
            'data' => new SiteGithubIntegrationResource($integration),
        ]);
    }
}
