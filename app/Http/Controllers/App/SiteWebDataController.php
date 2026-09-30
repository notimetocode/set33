<?php

namespace App\Http\Controllers\App;

use App\Enums\SiteWebDataStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\SiteResource;
use App\Jobs\CollectSiteWebDataJob;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SiteWebDataController extends Controller
{
    public function refresh(Site $site): JsonResponse
    {
        Gate::authorize('app.sites.update', $site);

        $site->forceFill([
            'web_data_status' => SiteWebDataStatus::Pending,
            'web_data_error' => null,
        ])->save();

        CollectSiteWebDataJob::dispatch($site->id);

        $site->load([
            'googleIntegration.googleConnection',
            'githubIntegration.githubConnection',
            'pagespeedIntegration.googleConnection',
        ]);

        return (new SiteResource($site->refresh()))
            ->response()
            ->setStatusCode(202);
    }
}
