<?php

namespace App\Http\Controllers\App;

use App\Actions\Site\UpdateSiteAiReportSharing;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\UpdateSiteAiReportSharingRequest;
use App\Http\Resources\App\SiteAiReportResource;
use App\Models\Site;
use App\Models\SiteAiReport;
use Illuminate\Support\Facades\Gate;

class SiteAiReportSharingController extends Controller
{
    public function update(
        UpdateSiteAiReportSharingRequest $request,
        Site $site,
        SiteAiReport $aiReport,
        UpdateSiteAiReportSharing $updateSharing,
    ): SiteAiReportResource {
        Gate::authorize('app.sites.update', $site);
        abort_unless($aiReport->site_id === $site->id, 404);

        $report = $updateSharing->handle($aiReport, $request->validated());

        return new SiteAiReportResource($report);
    }
}
