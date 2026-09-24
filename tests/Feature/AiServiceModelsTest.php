<?php

namespace Tests\Feature;

use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiServiceModelsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiModelsResponse(): array
    {
        return [
            'models' => [
                [
                    'name' => 'models/gemini-2.5-flash',
                    'displayName' => 'Gemini 2.5 Flash',
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                ],
                [
                    'name' => 'models/gemini-2.5-pro',
                    'displayName' => 'Gemini 2.5 Pro',
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                ],
                [
                    'name' => 'models/text-embedding-004',
                    'displayName' => 'Text Embedding 004',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ];
    }

    public function test_guest_cannot_list_models(): void
    {
        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
            'api_key' => 'test-key',
        ])->assertUnauthorized();
    }

    public function test_user_can_list_gemini_models_with_api_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response($this->geminiModelsResponse()),
        ]);

        $this->actingAsAppUser();

        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
            'api_key' => 'secret-gemini-key',
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'gemini-2.5-flash')
            ->assertJsonPath('data.0.name', 'Gemini 2.5 Flash')
            ->assertJsonPath('data.1.id', 'gemini-2.5-pro');

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('x-goog-api-key', 'secret-gemini-key')
                && str_contains($request->url(), '/v1beta/models');
        });
    }

    public function test_user_can_list_models_using_saved_service_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response($this->geminiModelsResponse()),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'stored-service-key',
        ]);

        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
            'ai_service_id' => $service->id,
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'stored-service-key'));
    }

    public function test_user_cannot_list_models_for_another_users_service(): void
    {
        $owner = User::factory()->create();
        $service = AiService::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
            'ai_service_id' => $service->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ai_service_id']);
    }

    public function test_invalid_api_key_returns_validation_error(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response(['error' => ['message' => 'API key not valid']], 400),
        ]);

        $this->actingAsAppUser();

        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
            'api_key' => 'bad-key',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    }

    public function test_models_endpoint_requires_api_key_or_service(): void
    {
        $this->actingAsAppUser();

        $this->postJson('/api/app/ai-services/models', [
            'type' => AiServiceType::Gemini->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key', 'ai_service_id']);
    }
}
