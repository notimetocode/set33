<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteGithubCommit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteGithubCommit>
 */
class SiteGithubCommitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sha = fake()->unique()->sha1();

        return [
            'site_id' => Site::factory(),
            'sha' => $sha,
            'message' => fake()->sentence(),
            'html_url' => 'https://github.com/octocat/hello-world/commit/'.$sha,
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'author_date' => now()->subDays(fake()->numberBetween(0, 30)),
            'committer_name' => fake()->name(),
            'committer_email' => fake()->safeEmail(),
            'committer_date' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
