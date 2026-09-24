<?php

namespace App\Http\Controllers\App;

use App\Actions\Github\ListGithubBranches;
use App\Actions\Github\ListGithubCommits;
use App\Actions\Github\ListGithubRepositories;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Github\ListGithubBranchesRequest;
use App\Http\Requests\App\Github\ListGithubCommitsRequest;
use App\Http\Requests\App\Github\ListGithubRepositoriesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class GithubRepositoryController extends Controller
{
    public function index(ListGithubRepositoriesRequest $request, ListGithubRepositories $list): JsonResponse
    {
        Gate::authorize('app.github.view');

        $connection = $request->user()->githubConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Сначала подключите аккаунт GitHub.'], 422);
        }

        try {
            return response()->json([
                'data' => $list->handle(
                    $connection,
                    (int) $request->validated('page', 1),
                    (int) $request->validated('per_page', 30),
                ),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось получить список репозиториев.',
            ], 422);
        }
    }

    public function branches(
        ListGithubBranchesRequest $request,
        string $owner,
        string $repo,
        ListGithubBranches $list,
    ): JsonResponse {
        Gate::authorize('app.github.view');

        $connection = $request->user()->githubConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Сначала подключите аккаунт GitHub.'], 422);
        }

        try {
            return response()->json([
                'data' => $list->handle(
                    $connection,
                    $owner,
                    $repo,
                    (int) $request->validated('page', 1),
                    (int) $request->validated('per_page', 30),
                ),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось получить список веток.',
            ], 422);
        }
    }

    public function commits(
        ListGithubCommitsRequest $request,
        string $owner,
        string $repo,
        ListGithubCommits $list,
    ): JsonResponse {
        Gate::authorize('app.github.view');

        $connection = $request->user()->githubConnection;

        if ($connection === null) {
            return response()->json(['message' => 'Сначала подключите аккаунт GitHub.'], 422);
        }

        try {
            return response()->json([
                'data' => $list->handle($connection, $owner, $repo, $request->filters()),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось получить коммиты репозитория.',
            ], 422);
        }
    }
}
