<?php

namespace Database\Factories;

use App\Enums\GithubConnectionStatus;
use App\Models\GithubConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubConnection>
 */
class GithubConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'github_user_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'github_login' => fake()->unique()->userName(),
            'github_account_email' => fake()->unique()->safeEmail(),
            'access_token' => 'test-github-access-token-'.fake()->uuid(),
            'refresh_token' => 'test-github-refresh-token-'.fake()->uuid(),
            'expires_at' => now()->addHour(),
            'scopes' => [
                'read:user',
                'user:email',
                'repo',
            ],
            'status' => GithubConnectionStatus::Active,
        ];
    }

    public function needsReauth(): static
    {
        return $this->state(fn (): array => [
            'status' => GithubConnectionStatus::NeedsReauth,
        ]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => null,
            'refresh_token' => null,
        ]);
    }
}
