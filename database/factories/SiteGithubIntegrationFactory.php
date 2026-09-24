<?php

namespace Database\Factories;

use App\Enums\SiteGithubIntegrationStatus;
use App\Models\GithubConnection;
use App\Models\Site;
use App\Models\SiteGithubIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteGithubIntegration>
 */
class SiteGithubIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = fake()->userName();
        $name = fake()->slug(2);

        return [
            'site_id' => Site::factory(),
            'github_connection_id' => GithubConnection::factory(),
            'repository_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'repository_owner' => $owner,
            'repository_name' => $name,
            'repository_full_name' => $owner.'/'.$name,
            'default_branch' => 'main',
            'status' => SiteGithubIntegrationStatus::Active,
        ];
    }
}
