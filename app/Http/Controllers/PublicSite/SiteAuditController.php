<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreSiteAuditRequest;
use App\Services\SiteAudit\WebsiteAnalyzer;
use Illuminate\Http\JsonResponse;

class SiteAuditController extends Controller
{
    public function store(StoreSiteAuditRequest $request, WebsiteAnalyzer $analyzer): JsonResponse
    {
        $report = $analyzer->analyze($request->validated('url'));

        return response()->json([
            'data' => $report->toArray(),
        ]);
    }
}
