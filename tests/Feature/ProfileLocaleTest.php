<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_includes_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        Sanctum::actingAs($user, ['app']);

        $this->getJson('/api/app/profile')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en');
    }

    public function test_user_can_update_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        Sanctum::actingAs($user, ['app']);

        $this->putJson('/api/app/profile', [
            'email' => $user->email,
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'en',
        ]);
    }

    public function test_update_rejects_invalid_locale(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['app']);

        $this->putJson('/api/app/profile', [
            'email' => $user->email,
            'locale' => 'de',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_api_uses_user_locale_for_validation_messages(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        Sanctum::actingAs($user, ['app']);

        $response = $this->putJson('/api/app/profile', [
            'email' => 'not-an-email',
            'locale' => 'ru',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $message = $response->json('errors.email.0');

        $this->assertIsString($message);
        $this->assertTrue(
            str_contains(mb_strtolower($message), 'email')
            || str_contains(mb_strtolower($message), 'e-mail')
            || str_contains($message, 'эл'),
            "Unexpected validation message: {$message}",
        );
    }
}
