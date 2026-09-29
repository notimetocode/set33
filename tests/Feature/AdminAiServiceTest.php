<?php

namespace Tests\Feature;

use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAiServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function geminiPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => AiServiceType::Gemini->value,
            'api_key' => 'admin-gemini-key-123',
            'settings' => [
                'model' => 'gemini-3.6-flash',
                'system_instruction' => 'Отвечай кратко',
                'generation_config' => [
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                    'top_k' => 32,
                    'max_output_tokens' => 2048,
                    'candidate_count' => 1,
                    'stop_sequences' => [],
                    'seed' => null,
                    'presence_penalty' => null,
                    'frequency_penalty' => null,
                    'response_mime_type' => 'text/plain',
                    'thinking_config' => [
                        'thinking_budget' => -1,
                    ],
                ],
            ],
        ], $overrides);
    }

    private function actingAsAdmin(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();

        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    public function test_guest_cannot_list_admin_ai_services(): void
    {
        $this->getJson('/api/admin/ai-services')->assertUnauthorized();
    }

    public function test_app_user_cannot_access_admin_ai_services(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['app']);

        $this->getJson('/api/admin/ai-services')->assertForbidden();
    }

    public function test_admin_can_create_and_list_global_ai_service(): void
    {
        $admin = $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/ai-services', $this->geminiPayload());

        $create->assertCreated()
            ->assertJsonPath('data.is_global', true)
            ->assertJsonPath('data.type', 'gemini')
            ->assertJsonMissingPath('data.api_key');

        $service = AiService::query()->first();
        $this->assertNotNull($service);
        $this->assertTrue($service->is_global);
        $this->assertSame($admin->id, $service->user_id);

        $this->getJson('/api/admin/ai-services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_global', true);
    }

    public function test_admin_index_does_not_include_personal_services(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        AiService::factory()->global()->create();
        AiService::factory()->for($user)->create();

        $this->getJson('/api/admin/ai-services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_global', true);
    }

    public function test_admin_can_update_and_delete_global_service(): void
    {
        $admin = $this->actingAsAdmin();
        $service = AiService::factory()->global()->for($admin)->create([
            'api_key' => 'original-key',
        ]);

        $this->putJson("/api/admin/ai-services/{$service->id}", $this->geminiPayload([
            'api_key' => null,
            'settings' => array_merge($service->settings, [
                'model' => 'gemini-2.5-pro',
            ]),
        ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Google Gemini · gemini-2.5-pro');

        $this->assertSame('original-key', $service->fresh()->api_key);

        $this->deleteJson("/api/admin/ai-services/{$service->id}")->assertNoContent();
        $this->assertDatabaseMissing('ai_services', ['id' => $service->id]);
    }

    public function test_admin_cannot_manage_personal_service_via_admin_api(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();
        $service = AiService::factory()->for($user)->create();

        $this->getJson("/api/admin/ai-services/{$service->id}")->assertForbidden();
        $this->putJson("/api/admin/ai-services/{$service->id}", $this->geminiPayload())->assertForbidden();
        $this->deleteJson("/api/admin/ai-services/{$service->id}")->assertForbidden();
    }

    public function test_admin_meta_returns_types(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/ai-services/meta')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'gemini',
                'label' => 'Google Gemini',
            ]);
    }
}
