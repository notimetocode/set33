<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_list_sites(): void
    {
        $this->getJson('/api/app/sites')->assertUnauthorized();
    }

    public function test_user_can_create_and_list_own_sites(): void
    {
        $user = $this->actingAsAppUser();

        $this->postJson('/api/app/sites', [
            'name' => 'Мой сайт',
            'url' => 'https://example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Мой сайт')
            ->assertJsonPath('data.url', 'https://example.com');

        $this->assertDatabaseHas('sites', [
            'user_id' => $user->id,
            'name' => 'Мой сайт',
        ]);

        $this->getJson('/api/app/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_view_another_users_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->getJson("/api/app/sites/{$site->id}")->assertForbidden();
    }

    public function test_user_can_update_and_delete_own_site(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'name' => 'Старое',
            'url' => 'https://old.example.com',
        ]);

        $this->putJson("/api/app/sites/{$site->id}", [
            'name' => 'Новое',
            'url' => 'https://new.example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Новое');

        $this->deleteJson("/api/app/sites/{$site->id}")->assertNoContent();
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_store_validates_url(): void
    {
        $this->actingAsAppUser();

        $this->postJson('/api/app/sites', [
            'name' => 'Сайт',
            'url' => 'not-a-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }
}
