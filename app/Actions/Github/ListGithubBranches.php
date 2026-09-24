<?php

namespace App\Actions\Github;

use App\Models\GithubConnection;
use App\Services\Github\GithubApiClient;

class ListGithubBranches
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    /**
     * @return list<array{
     *     name: string,
     *     protected: bool,
     *     commit_sha: ?string
     * }>
     */
    public function handle(
        GithubConnection $connection,
        string $owner,
        string $repo,
        int $page = 1,
        int $perPage = 30,
    ): array {
        return $this->githubApiClient->listBranches($connection, $owner, $repo, $page, $perPage);
    }
}
