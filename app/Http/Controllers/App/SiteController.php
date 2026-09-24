<?php

namespace App\Http\Controllers\App;

use App\Actions\Site\CreateSite;
use App\Actions\Site\DeleteSite;
use App\Actions\Site\UpdateSite;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\StoreSiteRequest;
use App\Http\Requests\App\Site\UpdateSiteRequest;
use App\Http\Resources\App\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('app.sites.viewAny');

        $sites = $request->user()
            ->sites()
            ->with([
                'googleIntegration.googleConnection',
                'githubIntegration.githubConnection',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request, CreateSite $create): JsonResponse
    {
        Gate::authorize('app.sites.create');

        $site = $create->handle($request->user(), $request->validated());
        $site->load([
            'googleIntegration.googleConnection',
            'githubIntegration.githubConnection',
        ]);

        return (new SiteResource($site))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Site $site): SiteResource
    {
        Gate::authorize('app.sites.view', $site);

        $site->load([
            'googleIntegration.googleConnection',
            'githubIntegration.githubConnection',
        ]);

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site, UpdateSite $update): SiteResource
    {
        Gate::authorize('app.sites.update', $site);

        $site = $update->handle($site, $request->validated());
        $site->load([
            'googleIntegration.googleConnection',
            'githubIntegration.githubConnection',
        ]);

        return new SiteResource($site);
    }

    public function destroy(Site $site, DeleteSite $delete): JsonResponse
    {
        Gate::authorize('app.sites.delete', $site);

        $delete->handle($site);

        return response()->json(null, 204);
    }
}
