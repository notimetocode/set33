<?php

namespace App\Actions\Github;

use App\Enums\SiteGithubIntegrationStatus;
use App\Models\SiteGithubCommit;
use App\Models\SiteGithubIntegration;
use App\Services\Github\GithubApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncSiteGithubCommits
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    public function handle(
        SiteGithubIntegration $integration,
        Carbon $startDate,
        Carbon $endDate,
    ): SiteGithubIntegration {
        $integration->loadMissing(['site', 'githubConnection']);

        $connection = $integration->githubConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту GitHub.');
        }

        if (! $integration->isConfigured()) {
            throw new RuntimeException('Сначала выберите репозиторий и ветку GitHub.');
        }

        $since = $startDate->copy()->startOfDay()->utc()->toIso8601String();
        $until = $endDate->copy()->addDay()->startOfDay()->utc()->toIso8601String();

        try {
            $commits = $this->githubApiClient->listAllCommits(
                $connection,
                $integration->repository_owner,
                $integration->repository_name,
                [
                    'sha' => $integration->default_branch,
                    'since' => $since,
                    'until' => $until,
                    'per_page' => 100,
                ],
            );

            DB::transaction(function () use ($integration, $commits): void {
                foreach ($commits as $commit) {
                    if ($commit['sha'] === '') {
                        continue;
                    }

                    SiteGithubCommit::query()->updateOrCreate(
                        [
                            'site_id' => $integration->site_id,
                            'sha' => $commit['sha'],
                        ],
                        [
                            'message' => $commit['message'],
                            'html_url' => $commit['html_url'] !== '' ? $commit['html_url'] : null,
                            'author_name' => $commit['author_name'],
                            'author_email' => $commit['author_email'],
                            'author_date' => $commit['author_date'],
                            'committer_name' => $commit['committer_name'],
                            'committer_email' => $commit['committer_email'],
                            'committer_date' => $commit['committer_date'],
                        ],
                    );
                }

                $integration->forceFill([
                    'status' => SiteGithubIntegrationStatus::Active,
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (Throwable $e) {
            $integration->forceFill([
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }

        return $integration->refresh()->load('githubConnection');
    }
}
