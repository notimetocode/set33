<?php

namespace App\Http\Controllers\App;

use App\Actions\Github\FetchSiteGithubCommitFiles;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteGithubCommit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SiteGithubCommitController extends Controller
{
    public function showFiles(Site $site, SiteGithubCommit $githubCommit): JsonResponse
    {
        Gate::authorize('app.sites.view', $site);
        $this->ensureCommitBelongsToSite($site, $githubCommit);

        if (! $githubCommit->hasFiles()) {
            return response()->json([
                'message' => 'Список изменений ещё не выгружен.',
            ], 404);
        }

        return response()->json([
            'data' => $this->serializeCommitFiles($githubCommit),
        ]);
    }

    public function fetchFiles(
        Site $site,
        SiteGithubCommit $githubCommit,
        FetchSiteGithubCommitFiles $fetch,
    ): JsonResponse {
        Gate::authorize('app.sites.update', $site);
        $this->ensureCommitBelongsToSite($site, $githubCommit);

        try {
            $commit = $fetch->handle($site, $githubCommit);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Не удалось выгрузить изменения коммита.',
            ], 422);
        }

        return response()->json([
            'data' => $this->serializeCommitFiles($commit),
        ]);
    }

    private function ensureCommitBelongsToSite(Site $site, SiteGithubCommit $commit): void
    {
        abort_unless((int) $commit->site_id === (int) $site->id, 404);
    }

    /**
     * @return array{
     *     id: int,
     *     sha: string,
     *     short_sha: string,
     *     message: string,
     *     html_url: ?string,
     *     author_name: ?string,
     *     author_date: ?string,
     *     stats: array{additions: int, deletions: int, total: int}|null,
     *     files: list<array<string, mixed>>,
     *     files_incomplete: bool,
     *     files_fetched_at: ?string
     * }
     */
    private function serializeCommitFiles(SiteGithubCommit $commit): array
    {
        /** @var list<array<string, mixed>> $files */
        $files = is_array($commit->files) ? $commit->files : [];

        /** @var array{additions?: int, deletions?: int, total?: int}|null $stats */
        $stats = is_array($commit->stats) ? $commit->stats : null;

        return [
            'id' => $commit->id,
            'sha' => $commit->sha,
            'short_sha' => substr($commit->sha, 0, 7),
            'message' => $commit->message,
            'html_url' => $commit->html_url,
            'author_name' => $commit->author_name,
            'author_date' => $commit->author_date?->toIso8601String(),
            'stats' => $stats === null ? null : [
                'additions' => (int) ($stats['additions'] ?? 0),
                'deletions' => (int) ($stats['deletions'] ?? 0),
                'total' => (int) ($stats['total'] ?? 0),
            ],
            'files' => $files,
            'files_incomplete' => (bool) $commit->files_incomplete,
            'files_fetched_at' => $commit->files_fetched_at?->toIso8601String(),
        ];
    }
}
