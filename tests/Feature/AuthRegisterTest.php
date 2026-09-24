<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_receive_app_token(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/app/auth/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ivan@example.com')
            ->assertJsonPath('user.name', 'Иван')
            ->assertJsonPath('user.role', UserRole::User->value);

        $this->assertDatabaseHas('users', [
            'email' => 'ivan@example.com',
            'name' => 'Иван',
            'first_name' => 'Иван',
            'role' => UserRole::User->value,
        ]);

        Event::assertDispatched(Registered::class);

        $user = User::query()->where('email', 'ivan@example.com')->firstOrFail();

        $this->withToken($response->json('token'))
            ->getJson('/api/app/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/app/auth/register', [
            'name' => 'Иван',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/app/auth/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'other-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_always_creates_user_role(): void
    {
        $this->postJson('/api/app/auth/register', [
            'name' => 'Админ',
            'email' => 'admin-wannabe@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::User->value);

        $this->assertDatabaseHas('users', [
            'email' => 'admin-wannabe@example.com',
            'role' => UserRole::User->value,
        ]);
    }
}
