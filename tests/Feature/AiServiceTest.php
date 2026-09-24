<?php

namespace Tests\Feature;

use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function geminiPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => AiServiceType::Gemini->value,
            'api_key' => 'secret-gemini-key-123',
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

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_list_ai_services(): void
    {
        $this->getJson('/api/app/ai-services')->assertUnauthorized();
    }

    public function test_user_can_create_and_list_own_ai_service(): void
    {
        $user = $this->actingAsAppUser();

        $create = $this->postJson('/api/app/ai-services', $this->geminiPayload());

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Google Gemini · gemini-3.6-flash')
            ->assertJsonPath('data.type', 'gemini')
            ->assertJsonPath('data.settings.model', 'gemini-3.6-flash')
            ->assertJsonPath('data.api_key_set', true)
            ->assertJsonMissingPath('data.api_key');

        $this->assertDatabaseCount('ai_services', 1);

        $service = AiService::query()->first();
        $this->assertSame($user->id, $service->user_id);
        $this->assertSame('secret-gemini-key-123', $service->api_key);

        $this->getJson('/api/app/ai-services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.api_key');
    }

    public function test_user_can_update_ai_service_without_changing_api_key(): void
    {
        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'original-key',
            'name' => 'Старое имя',
        ]);

        $payload = $this->geminiPayload([
            'api_key' => null,
            'settings' => array_merge($service->settings, [
                'model' => 'gemini-2.5-pro',
            ]),
        ]);

        $this->putJson("/api/app/ai-services/{$service->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Google Gemini · gemini-2.5-pro')
            ->assertJsonPath('data.settings.model', 'gemini-2.5-pro')
            ->assertJsonMissingPath('data.api_key');

        $this->assertSame('original-key', $service->fresh()->api_key);
    }

    public function test_user_can_replace_api_key(): void
    {
        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'original-key',
        ]);

        $this->putJson("/api/app/ai-services/{$service->id}", $this->geminiPayload([
            'api_key' => 'replacement-key',
        ]))->assertOk();

        $this->assertSame('replacement-key', $service->fresh()->api_key);
    }

    public function test_user_cannot_view_another_users_ai_service(): void
    {
        $owner = User::factory()->create();
        $service = AiService::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->getJson("/api/app/ai-services/{$service->id}")->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_ai_service(): void
    {
        $owner = User::factory()->create();
        $service = AiService::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->deleteJson("/api/app/ai-services/{$service->id}")->assertForbidden();
        $this->assertDatabaseHas('ai_services', ['id' => $service->id]);
    }

    public function test_user_can_delete_own_ai_service(): void
    {
        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create();

        $this->deleteJson("/api/app/ai-services/{$service->id}")->assertNoContent();
        $this->assertDatabaseMissing('ai_services', ['id' => $service->id]);
    }

    public function test_store_requires_api_key_and_model(): void
    {
        $this->actingAsAppUser();

        $this->postJson('/api/app/ai-services', [
            'type' => AiServiceType::Gemini->value,
            'settings' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key', 'settings.model', 'settings.generation_config']);
    }

    public function test_meta_returns_available_types(): void
    {
        $this->actingAsAppUser();

        $this->getJson('/api/app/ai-services/meta')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'gemini',
                'label' => 'Google Gemini',
            ]);
    }
}
