<?php

namespace App\Http\Controllers\App;

use App\Actions\Google\DisconnectGoogleConnection;
use App\Actions\Google\StartGoogleOAuth;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Google\StartGoogleOAuthRequest;
use App\Http\Resources\App\GoogleConnectionResource;
use App\Services\Google\GoogleApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class GoogleConnectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('app.google.view');

        $connection = $request->user()->googleConnection;

        return response()->json([
            'data' => $connection ? new GoogleConnectionResource($connection) : null,
        ]);
    }

    public function start(StartGoogleOAuthRequest $request, StartGoogleOAuth $start): JsonResponse
    {
        Gate::authorize('app.google.connect');

        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret'))) {
            return response()->json([
                'message' => 'OAuth Google не настроен на сервере.',
            ], 503);
        }

        $result = $start->handle(
            $request->user(),
            $request->validated('return_site_id'),
        );

        return response()->json($result);
    }

    public function destroy(Request $request, DisconnectGoogleConnection $disconnect): JsonResponse
    {
        Gate::authorize('app.google.disconnect');

        $connection = $request->user()->googleConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Аккаунт Google не подключён.'], 404);
        }

        $disconnect->handle($connection);

        return response()->json(null, 204);
    }

    public function ga4Properties(Request $request, GoogleApiClient $client): JsonResponse
    {
        Gate::authorize('app.google.view');

        $connection = $request->user()->googleConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Сначала подключите аккаунт Google.'], 422);
        }

        try {
            return response()->json([
                'data' => $client->listGa4Properties($connection),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось получить список GA4 property.',
            ], 422);
        }
    }

    public function gscSites(Request $request, GoogleApiClient $client): JsonResponse
    {
        Gate::authorize('app.google.view');

        $connection = $request->user()->googleConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Сначала подключите аккаунт Google.'], 422);
        }

        try {
            return response()->json([
                'data' => $client->listGscSites($connection),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось получить список сайтов Search Console.',
            ], 422);
        }
    }
}
