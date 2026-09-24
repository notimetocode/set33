<?php

namespace App\Http\Controllers\App;

use App\Actions\Github\DisconnectGithubConnection;
use App\Actions\Github\StartGithubOAuth;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Github\StartGithubOAuthRequest;
use App\Http\Resources\App\GithubConnectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GithubConnectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('app.github.view');

        $connection = $request->user()->githubConnection;

        return response()->json([
            'data' => $connection ? new GithubConnectionResource($connection) : null,
        ]);
    }

    public function start(StartGithubOAuthRequest $request, StartGithubOAuth $start): JsonResponse
    {
        Gate::authorize('app.github.connect');

        if (! filled(config('services.github.client_id')) || ! filled(config('services.github.client_secret'))) {
            return response()->json([
                'message' => 'OAuth GitHub не настроен на сервере.',
            ], 503);
        }

        return response()->json($start->handle(
            $request->user(),
            $request->validated('return_site_id'),
        ));
    }

    public function destroy(Request $request, DisconnectGithubConnection $disconnect): JsonResponse
    {
        Gate::authorize('app.github.disconnect');

        $connection = $request->user()->githubConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Аккаунт GitHub не подключён.'], 404);
        }

        $disconnect->handle($connection);

        return response()->json(null, 204);
    }
}
