<?php

namespace App\Http\Controllers\App;

use App\Actions\Site\CreateSiteDocument;
use App\Actions\Site\DeleteSiteDocument;
use App\Actions\Site\UpdateSiteDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\StoreSiteDocumentRequest;
use App\Http\Requests\App\Site\UpdateSiteDocumentRequest;
use App\Http\Resources\App\SiteDocumentResource;
use App\Models\Site;
use App\Models\SiteDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SiteDocumentController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        Gate::authorize('app.sites.view', $site);

        $documents = $site->documents()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SiteDocumentResource::collection($documents);
    }

    public function store(
        StoreSiteDocumentRequest $request,
        Site $site,
        CreateSiteDocument $create,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);

        $document = $create->handle($site, $request->validated());

        return (new SiteDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateSiteDocumentRequest $request,
        Site $site,
        SiteDocument $document,
        UpdateSiteDocument $update,
    ): SiteDocumentResource {
        Gate::authorize('app.sites.update', $site);
        abort_unless($document->site_id === $site->id, 404);

        $document = $update->handle($document, $request->validated());

        return new SiteDocumentResource($document);
    }

    public function destroy(
        Site $site,
        SiteDocument $document,
        DeleteSiteDocument $delete,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);
        abort_unless($document->site_id === $site->id, 404);

        $delete->handle($document);

        return response()->json(null, 204);
    }
}
