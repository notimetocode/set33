<?php

namespace App\Services\Github;

use App\Enums\GithubConnectionStatus;
use App\Models\GithubConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubApiClient
{
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
    public function listRepositories(GithubConnection $connection, int $page = 1, int $perPage = 30): array
    {
        $response = $this->authenticatedRequest($connection)
            ->get('/user/repos', [
                'affiliation' => 'owner,collaborator,organization_member',
                'sort' => 'updated',
                'direction' => 'desc',
                'page' => $page,
                'per_page' => min($perPage, 100),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->status(), 'Не удалось получить список репозиториев GitHub.'));
        }

        /** @var list<array<string, mixed>> $repos */
        $repos = $response->json() ?? [];

        return array_map(fn (array $repo): array => [
            'id' => (int) ($repo['id'] ?? 0),
            'name' => (string) ($repo['name'] ?? ''),
            'full_name' => (string) ($repo['full_name'] ?? ''),
            'private' => (bool) ($repo['private'] ?? false),
            'html_url' => (string) ($repo['html_url'] ?? ''),
            'description' => isset($repo['description']) && is_string($repo['description']) ? $repo['description'] : null,
            'default_branch' => isset($repo['default_branch']) && is_string($repo['default_branch']) ? $repo['default_branch'] : null,
            'updated_at' => isset($repo['updated_at']) && is_string($repo['updated_at']) ? $repo['updated_at'] : null,
        ], $repos);
    }

    /**
     * @return list<array{
     *     name: string,
     *     protected: bool,
     *     commit_sha: ?string
     * }>
     */
    public function listBranches(
        GithubConnection $connection,
        string $owner,
        string $repo,
        int $page = 1,
        int $perPage = 30,
    ): array {
        $response = $this->authenticatedRequest($connection)
            ->get('/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/branches', [
                'page' => $page,
                'per_page' => min($perPage, 100),
            ]);

        if ($response->status() === 404) {
            throw new RuntimeException('Репозиторий не найден или нет доступа.');
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->status(), 'Не удалось получить список веток репозитория.'));
        }

        /** @var list<array<string, mixed>> $branches */
        $branches = $response->json() ?? [];

        return array_map(function (array $branch): array {
            /** @var array<string, mixed> $commit */
            $commit = is_array($branch['commit'] ?? null) ? $branch['commit'] : [];

            return [
                'name' => (string) ($branch['name'] ?? ''),
                'protected' => (bool) ($branch['protected'] ?? false),
                'commit_sha' => isset($commit['sha']) && is_string($commit['sha']) ? $commit['sha'] : null,
            ];
        }, $branches);
    }

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
    public function listCommits(GithubConnection $connection, string $owner, string $repo, array $filters = []): array
    {
        $query = array_filter([
            'sha' => $filters['sha'] ?? null,
            'since' => $filters['since'] ?? null,
            'until' => $filters['until'] ?? null,
            'page' => $filters['page'] ?? 1,
            'per_page' => min((int) ($filters['per_page'] ?? 30), 100),
        ], fn ($value): bool => $value !== null && $value !== '');

        $response = $this->authenticatedRequest($connection)
            ->get('/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/commits', $query);

        if ($response->status() === 404) {
            throw new RuntimeException('Репозиторий не найден или нет доступа.');
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->status(), 'Не удалось получить коммиты репозитория.'));
        }

        /** @var list<array<string, mixed>> $commits */
        $commits = $response->json() ?? [];

        return array_map(function (array $item): array {
            /** @var array<string, mixed> $commit */
            $commit = is_array($item['commit'] ?? null) ? $item['commit'] : [];
            /** @var array<string, mixed> $author */
            $author = is_array($commit['author'] ?? null) ? $commit['author'] : [];
            /** @var array<string, mixed> $committer */
            $committer = is_array($commit['committer'] ?? null) ? $commit['committer'] : [];

            return [
                'sha' => (string) ($item['sha'] ?? ''),
                'message' => (string) ($commit['message'] ?? ''),
                'html_url' => (string) ($item['html_url'] ?? ''),
                'author_name' => isset($author['name']) && is_string($author['name']) ? $author['name'] : null,
                'author_email' => isset($author['email']) && is_string($author['email']) ? $author['email'] : null,
                'author_date' => isset($author['date']) && is_string($author['date']) ? $author['date'] : null,
                'committer_name' => isset($committer['name']) && is_string($committer['name']) ? $committer['name'] : null,
                'committer_email' => isset($committer['email']) && is_string($committer['email']) ? $committer['email'] : null,
                'committer_date' => isset($committer['date']) && is_string($committer['date']) ? $committer['date'] : null,
            ];
        }, $commits);
    }

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
    public function listAllCommits(GithubConnection $connection, string $owner, string $repo, array $filters = []): array
    {
        $page = 1;
        $perPage = min((int) ($filters['per_page'] ?? 100), 100);
        $all = [];

        do {
            $batch = $this->listCommits($connection, $owner, $repo, [
                ...$filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($batch as $commit) {
                $all[] = $commit;
            }

            $page++;
        } while (count($batch) === $perPage && $page <= 50);

        return $all;
    }

    public function revoke(GithubConnection $connection): void
    {
        $clientId = (string) config('services.github.client_id');
        $clientSecret = (string) config('services.github.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return;
        }

        Http::withBasicAuth($clientId, $clientSecret)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15)
            ->delete('https://api.github.com/applications/'.$clientId.'/token', [
                'access_token' => $connection->access_token,
            ]);
    }

    private function authenticatedRequest(GithubConnection $connection): PendingRequest
    {
        $this->ensureFreshToken($connection);

        $baseUrl = rtrim((string) config('services.github.api_base_url'), '/');

        return Http::baseUrl($baseUrl)
            ->withToken((string) $connection->access_token)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(3)
            ->timeout(20)
            ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false);
    }

    private function ensureFreshToken(GithubConnection $connection): void
    {
        if (! $connection->accessTokenExpired()) {
            return;
        }

        if (! filled($connection->refresh_token)) {
            $connection->forceFill([
                'status' => GithubConnectionStatus::NeedsReauth,
            ])->save();

            throw new RuntimeException('Требуется повторная авторизация GitHub.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            $connection->forceFill([
                'status' => GithubConnectionStatus::NeedsReauth,
            ])->save();

            throw new RuntimeException('Требуется повторная авторизация GitHub.');
        }

        $connection->forceFill([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => filled($response->json('refresh_token'))
                ? (string) $response->json('refresh_token')
                : $connection->refresh_token,
            'expires_at' => $response->json('expires_in')
                ? now()->addSeconds((int) $response->json('expires_in'))
                : null,
            'status' => GithubConnectionStatus::Active,
        ])->save();
    }

    private function errorMessage(int $status, string $fallback): string
    {
        return match ($status) {
            401, 403 => 'Нет доступа к GitHub. Подключите аккаунт заново.',
            404 => 'Ресурс GitHub не найден.',
            429 => 'Превышен лимит запросов GitHub. Попробуйте позже.',
            default => $fallback,
        };
    }
}
