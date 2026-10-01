<?php

namespace App\Actions\Github;

use App\Models\Site;
use App\Models\SiteGithubCommit;
use App\Services\Github\GithubApiClient;
use RuntimeException;

class FetchSiteGithubCommitFiles
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    public function handle(Site $site, SiteGithubCommit $commit, bool $followPagination = false): SiteGithubCommit
    {
        if ((int) $commit->site_id !== (int) $site->id) {
            throw new RuntimeException('Коммит не принадлежит этому сайту.');
        }

        $integration = $site->githubIntegration;

        if ($integration === null || ! $integration->isConfigured()) {
            throw new RuntimeException('Сначала привяжите репозиторий и ветку GitHub к сайту.');
        }

        $integration->loadMissing('githubConnection');
        $connection = $integration->githubConnection;

        if ($connection === null) {
            throw new RuntimeException('Интеграция не привязана к аккаунту GitHub.');
        }

        $detail = $this->githubApiClient->getCommit(
            $connection,
            $integration->repository_owner,
            $integration->repository_name,
            $commit->sha,
            $followPagination,
        );

        $commit->forceFill([
            'files' => $detail['files'],
            'stats' => $detail['stats'],
            'files_incomplete' => $detail['files_incomplete'],
            'files_fetched_at' => now(),
        ])->save();

        return $commit->refresh();
    }
}
