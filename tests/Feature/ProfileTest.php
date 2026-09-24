<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/app/profile')->assertUnauthorized();
    }

    public function test_user_can_view_own_profile(): void
    {
        $user = $this->actingAsAppUser(User::factory()->create([
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'email' => 'ivan@example.com',
        ]));

        $this->getJson('/api/app/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.last_name', 'Иванов')
            ->assertJsonPath('data.first_name', 'Иван')
            ->assertJsonPath('data.email', 'ivan@example.com')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'last_name',
                    'first_name',
                    'middle_name',
                    'phone',
                    'telegram',
                    'viber',
                ],
            ]);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = $this->actingAsAppUser();

        $this->putJson('/api/app/profile', [
            'last_name' => 'Петров',
            'first_name' => 'Пётр',
            'middle_name' => 'Петрович',
            'email' => 'petr@example.com',
            'phone' => '+375291111111',
            'telegram' => '@petr',
            'viber' => '+375291111111',
        ])
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Петров')
            ->assertJsonPath('data.first_name', 'Пётр')
            ->assertJsonPath('data.email', 'petr@example.com')
            ->assertJsonPath('data.phone', '+375291111111');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_name' => 'Петров',
            'first_name' => 'Пётр',
            'email' => 'petr@example.com',
            'name' => 'Петров Пётр Петрович',
        ]);
    }

    public function test_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAsAppUser();

        $this->putJson('/api/app/profile', [
            'email' => 'taken@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->putJson('/api/app/profile', [
            'email' => 'guest@example.com',
        ])->assertUnauthorized();
    }
}
