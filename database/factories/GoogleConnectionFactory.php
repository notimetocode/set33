<?php

namespace Database\Factories;

use App\Enums\GoogleConnectionStatus;
use App\Models\GoogleConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleConnection>
 */
class GoogleConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_account_email' => fake()->unique()->safeEmail(),
            'access_token' => 'test-access-token-'.fake()->uuid(),
            'refresh_token' => 'test-refresh-token-'.fake()->uuid(),
            'expires_at' => now()->addHour(),
            'scopes' => [
                'https://www.googleapis.com/auth/analytics.readonly',
                'https://www.googleapis.com/auth/webmasters.readonly',
            ],
            'status' => GoogleConnectionStatus::Active,
        ];
    }

    public function needsReauth(): static
    {
        return $this->state(fn (): array => [
            'status' => GoogleConnectionStatus::NeedsReauth,
        ]);
    }
}
