<?php

namespace App\Http\Controllers\App;

use App\Actions\Site\ApplySiteAiReportPreprocessItems;
use App\Actions\Site\GenerateSiteAiReport;
use App\Actions\Site\PreprocessSiteAiReport;
use App\Actions\Site\SaveSiteAiReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Site\ApplySiteAiReportPreprocessRequest;
use App\Http\Requests\App\Site\GenerateSiteAiReportRequest;
use App\Http\Requests\App\Site\PreprocessSiteAiReportRequest;
use App\Http\Resources\App\SiteAiReportResource;
use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAiReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SiteAiReportController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        Gate::authorize('app.sites.view', $site);

        $reports = $site->aiReports()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SiteAiReportResource::collection($reports);
    }

    public function show(Site $site, SiteAiReport $aiReport): SiteAiReportResource
    {
        Gate::authorize('app.sites.view', $site);
        abort_unless($aiReport->site_id === $site->id, 404);

        return new SiteAiReportResource($aiReport);
    }

    public function store(
        GenerateSiteAiReportRequest $request,
        Site $site,
        GenerateSiteAiReport $generate,
        SaveSiteAiReport $save,
    ): JsonResponse {
        Gate::authorize('app.sites.view', $site);

        $validated = $request->validated();
        $aiService = AiService::query()->findOrFail($validated['ai_service_id']);

        Gate::authorize('app.ai-services.generate', $aiService);

        $result = $generate->handle(
            $site,
            $aiService,
            $validated['from'],
            $validated['to'],
            (bool) ($validated['use_system_prompt'] ?? true),
            $validated['prompt'] ?? null,
        );

        if (! $result['ok'] || ! filled($result['reply'])) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Не удалось сформировать отчёт.',
                'retryable' => (bool) ($result['retryable'] ?? false),
                'period' => $result['period'],
                'data_counts' => $result['data_counts'],
                'data' => null,
            ]);
        }

        $report = $save->handle($site, $aiService, [
            'reply' => $result['reply'],
            'charts' => $result['charts'] ?? [],
            'model' => $result['model'],
            'usage' => $result['usage'],
            'period' => $result['period'],
            'data_counts' => $result['data_counts'],
            'use_system_prompt' => $result['use_system_prompt'],
            'prompt' => $result['prompt'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => null,
            'period' => $result['period'],
            'data_counts' => $result['data_counts'],
            'data' => (new SiteAiReportResource($report))->resolve(),
        ], 201);
    }

    public function preprocess(
        PreprocessSiteAiReportRequest $request,
        Site $site,
        PreprocessSiteAiReport $preprocess,
    ): JsonResponse {
        Gate::authorize('app.sites.view', $site);

        $validated = $request->validated();
        $aiService = AiService::query()->findOrFail($validated['ai_service_id']);

        Gate::authorize('app.ai-services.generate', $aiService);

        $result = $preprocess->handle(
            $site,
            $aiService,
            $validated['from'],
            $validated['to'],
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'retryable' => (bool) ($result['retryable'] ?? false),
            'period' => $result['period'],
            'model' => $result['model'],
            'usage' => $result['usage'],
            'items' => $result['items'],
        ]);
    }

    public function applyPreprocess(
        ApplySiteAiReportPreprocessRequest $request,
        Site $site,
        ApplySiteAiReportPreprocessItems $apply,
    ): JsonResponse {
        Gate::authorize('app.sites.view', $site);

        $validated = $request->validated();
        $result = $apply->handle(
            $site,
            $validated['from'],
            $validated['to'],
            $validated['items'],
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'results' => $result['results'],
        ]);
    }
}
