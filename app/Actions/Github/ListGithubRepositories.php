<?php

namespace App\Actions\Github;

use App\Models\GithubConnection;
use App\Services\Github\GithubApiClient;

class ListGithubRepositories
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     full_name: string,
     *     private: bool,
     *     html_url: string,
     *     description: ?string,
     *     default_branch: ?string,
     *     updated_at: ?string
     * }>
     */
    public function handle(GithubConnection $connection, int $page = 1, int $perPage = 30): array
    {
        return $this->githubApiClient->listRepositories($connection, $page, $perPage);
    }
}
