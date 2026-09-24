<?php

namespace App\Http\Controllers\App;

use App\Actions\Site\CreateSiteEvent;
use App\Actions\Site\DeleteSiteEvent;
use App\Actions\Site\UpdateSiteEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\SiteMetricsRequest;
use App\Http\Requests\App\Site\StoreSiteEventRequest;
use App\Http\Requests\App\Site\UpdateSiteEventRequest;
use App\Http\Resources\App\SiteEventResource;
use App\Models\Site;
use App\Models\SiteEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SiteEventController extends Controller
{
    public function index(SiteMetricsRequest $request, Site $site): AnonymousResourceCollection
    {
        Gate::authorize('app.sites.view', $site);

        $from = $request->date('from')->toDateString();
        $to = $request->date('to')->toDateString();

        $events = $site->events()
            ->whereBetween('occurred_on', [$from, $to])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        return SiteEventResource::collection($events);
    }

    public function store(
        StoreSiteEventRequest $request,
        Site $site,
        CreateSiteEvent $create,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        $event = $create->handle($site, $request->validated());

        return (new SiteEventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateSiteEventRequest $request,
        Site $site,
        SiteEvent $event,
        UpdateSiteEvent $update,
    ): SiteEventResource {
        Gate::authorize('app.sites.update', $site);
        abort_unless($event->site_id === $site->id, 404);

        $event = $update->handle($event, $request->validated());

        return new SiteEventResource($event);
    }

    public function destroy(
        Site $site,
        SiteEvent $event,
        DeleteSiteEvent $delete,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);
        abort_unless($event->site_id === $site->id, 404);

        $delete->handle($event);

        return response()->json(null, 204);
    }
}
