<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AiService\CheckAiService;
use App\Actions\AiService\CreateAiService;
use App\Actions\AiService\DeleteAiService;
use App\Actions\AiService\ListAiServiceModels;
use App\Actions\AiService\UpdateAiService;
use App\Enums\AiServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiService\ListAiServiceModelsRequest;
use App\Http\Requests\Admin\AiService\StoreAiServiceRequest;
use App\Http\Requests\Admin\AiService\UpdateAiServiceRequest;
use App\Http\Resources\Admin\AiServiceResource;
use App\Models\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('admin.ai-services.viewAny');

        $services = AiService::query()
            ->global()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AiServiceResource::collection($services);
    }

    public function store(StoreAiServiceRequest $request, CreateAiService $create): JsonResponse
    {
        Gate::authorize('admin.ai-services.create');

        $service = $create->handle($request->user(), $request->validated(), true);

        return (new AiServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AiService $aiService): AiServiceResource
    {
        Gate::authorize('admin.ai-services.view', $aiService);

        return new AiServiceResource($aiService);
    }

    public function update(
        UpdateAiServiceRequest $request,
        AiService $aiService,
        UpdateAiService $update,
    ): AiServiceResource {
        Gate::authorize('admin.ai-services.update', $aiService);

        $service = $update->handle($aiService, $request->validated());

        return new AiServiceResource($service);
    }

    public function destroy(AiService $aiService, DeleteAiService $delete): JsonResponse
    {
        Gate::authorize('admin.ai-services.delete', $aiService);

        $delete->handle($aiService);

        return response()->json(null, 204);
    }

    public function check(AiService $aiService, CheckAiService $check): JsonResponse
    {
        Gate::authorize('admin.ai-services.check', $aiService);

        $result = $check->handle($aiService);

        return response()->json([
            'ok' => $result['ok'],
            'title' => $result['title'],
            'message' => $result['message'],
            'reply' => $result['reply'],
            'data' => new AiServiceResource($result['service']),
        ]);
    }

    public function meta(): JsonResponse
    {
        Gate::authorize('admin.ai-services.viewAny');

        return response()->json([
            'types' => AiServiceType::options(),
            'gemini' => [
                'preferred_models' => array_values(config('services.gemini.preferred_models', [])),
            ],
            'groq' => [
                'preferred_models' => array_values(config('services.groq.preferred_models', [])),
            ],
        ]);
    }

    public function models(ListAiServiceModelsRequest $request, ListAiServiceModels $list): JsonResponse
    {
        Gate::authorize('admin.ai-services.viewAny');

        $validated = $request->validated();
        $apiKey = $validated['api_key'] ?? null;

        if (! filled($apiKey)) {
            $service = AiService::query()->findOrFail($validated['ai_service_id']);
            Gate::authorize('admin.ai-services.view', $service);
            $apiKey = $service->api_key;
        }

        $type = AiServiceType::from($validated['type']);

        return response()->json([
            'data' => $list->handle($type, (string) $apiKey),
        ]);
    }
}
