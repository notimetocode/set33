<?php

namespace App\Http\Controllers\App;

use App\Actions\AiService\CheckAiService;
use App\Actions\AiService\CreateAiService;
use App\Actions\AiService\DeleteAiService;
use App\Actions\AiService\GenerateAiServiceContent;
use App\Actions\AiService\ListGeminiModels;
use App\Actions\AiService\UpdateAiService;
use App\Enums\AiServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AiService\GenerateAiServiceContentRequest;
use App\Http\Requests\App\AiService\ListAiServiceModelsRequest;
use App\Http\Requests\App\AiService\StoreAiServiceRequest;
use App\Http\Requests\App\AiService\UpdateAiServiceRequest;
use App\Http\Resources\App\AiServiceResource;
use App\Models\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('app.ai-services.viewAny');

        $services = $request->user()
            ->aiServices()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AiServiceResource::collection($services);
    }

    public function store(StoreAiServiceRequest $request, CreateAiService $create): JsonResponse
    {
        Gate::authorize('app.ai-services.create');

        $service = $create->handle($request->user(), $request->validated());

        return (new AiServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AiService $aiService): AiServiceResource
    {
        Gate::authorize('app.ai-services.view', $aiService);

        return new AiServiceResource($aiService);
    }

    public function update(
        UpdateAiServiceRequest $request,
        AiService $aiService,
        UpdateAiService $update,
    ): AiServiceResource {
        Gate::authorize('app.ai-services.update', $aiService);

        $service = $update->handle($aiService, $request->validated());

        return new AiServiceResource($service);
    }

    public function destroy(AiService $aiService, DeleteAiService $delete): JsonResponse
    {
        Gate::authorize('app.ai-services.delete', $aiService);

        $delete->handle($aiService);

        return response()->json(null, 204);
    }

    public function check(AiService $aiService, CheckAiService $check): JsonResponse
    {
        Gate::authorize('app.ai-services.check', $aiService);

        $result = $check->handle($aiService);

        return response()->json([
            'ok' => $result['ok'],
            'title' => $result['title'],
            'message' => $result['message'],
            'reply' => $result['reply'],
            'data' => new AiServiceResource($result['service']),
        ]);
    }

    public function generate(
        GenerateAiServiceContentRequest $request,
        AiService $aiService,
        GenerateAiServiceContent $generate,
    ): JsonResponse {
        Gate::authorize('app.ai-services.generate', $aiService);

        $result = $generate->handle($aiService, $request->validated('prompt'));

        return response()->json([
            'ok' => $result['ok'],
            'reply' => $result['reply'],
            'message' => $result['message'],
            'model' => $result['model'],
            'usage' => $result['usage'],
        ]);
    }

    public function meta(): JsonResponse
    {
        Gate::authorize('app.ai-services.viewAny');

        return response()->json([
            'types' => AiServiceType::options(),
            'gemini' => [
                'preferred_models' => array_values(config('services.gemini.preferred_models', [])),
            ],
        ]);
    }

    public function models(ListAiServiceModelsRequest $request, ListGeminiModels $list): JsonResponse
    {
        Gate::authorize('app.ai-services.viewAny');

        $validated = $request->validated();
        $apiKey = $validated['api_key'] ?? null;

        if (! filled($apiKey)) {
            $service = AiService::query()->findOrFail($validated['ai_service_id']);
            Gate::authorize('app.ai-services.view', $service);
            $apiKey = $service->api_key;
        }

        return response()->json([
            'data' => $list->handle((string) $apiKey),
        ]);
    }
}
