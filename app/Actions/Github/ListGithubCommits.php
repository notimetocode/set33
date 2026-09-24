<?php

namespace App\Actions\Github;

use App\Models\GithubConnection;
use App\Services\Github\GithubApiClient;

class ListGithubCommits
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    /**
     * @param  array{sha?: string, since?: string, until?: string, page?: int, per_page?: int}  $filters
     * @return list<array{
     *     sha: string,
     *     message: string,
     *     html_url: string,
     *     author_name: ?string,
     *     author_email: ?string,
     *     author_date: ?string,
     *     committer_name: ?string,
     *     committer_email: ?string,
     *     committer_date: ?string
     * }>
     */
    public function handle(GithubConnection $connection, string $owner, string $repo, array $filters = []): array
    {
        return $this->githubApiClient->listCommits($connection, $owner, $repo, $filters);
    }
}
