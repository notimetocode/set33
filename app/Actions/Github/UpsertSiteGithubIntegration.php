<?php

namespace App\Actions\Github;

use App\Enums\SiteGithubIntegrationStatus;
use App\Models\Site;
use App\Models\SiteGithubIntegration;
use App\Models\User;
use InvalidArgumentException;

class UpsertSiteGithubIntegration
{
    /**
     * @param  array{
     *     repository_full_name: string,
     *     repository_id?: int|null,
     *     default_branch: string
     * }  $data
     */
    public function handle(User $user, Site $site, array $data): SiteGithubIntegration
    {
        $connection = $user->githubConnection;

        if ($connection === null) {
            throw new InvalidArgumentException('Сначала подключите аккаунт GitHub.');
        }

        $fullName = trim((string) ($data['repository_full_name'] ?? ''));

        if ($fullName === '' || ! str_contains($fullName, '/')) {
            throw new InvalidArgumentException('Выберите репозиторий GitHub.');
        }

        [$owner, $name] = explode('/', $fullName, 2);
        $owner = trim($owner);
        $name = trim($name);

        if ($owner === '' || $name === '') {
            throw new InvalidArgumentException('Некорректное имя репозитория.');
        }

        $defaultBranch = isset($data['default_branch']) ? trim((string) $data['default_branch']) : '';

        if ($defaultBranch === '') {
            throw new InvalidArgumentException('Выберите ветку.');
        }

        $repositoryId = isset($data['repository_id']) ? (int) $data['repository_id'] : null;

        if ($repositoryId !== null && $repositoryId < 1) {
            $repositoryId = null;
        }

        $integration = SiteGithubIntegration::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'github_connection_id' => $connection->id,
                'repository_id' => $repositoryId,
                'repository_owner' => $owner,
                'repository_name' => $name,
                'repository_full_name' => $owner.'/'.$name,
                'default_branch' => $defaultBranch,
                'status' => SiteGithubIntegrationStatus::Active,
            ],
        );

        return $integration->load('githubConnection');
    }
}
