<?php

namespace Tests\Feature;

use App\Models\AiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiServiceGenerateTest extends TestCase
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
    private function generateContentResponse(string $text = 'Привет!'): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 12,
                'candidatesTokenCount' => 4,
                'totalTokenCount' => 16,
            ],
        ];
    }

    public function test_guest_cannot_generate_content(): void
    {
        $service = AiService::factory()->create();

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Привет',
        ])->assertUnauthorized();
    }

    public function test_user_can_generate_content_for_own_service(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse('Ответ модели'),
            ),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-gemini-key',
            'settings' => [
                'model' => 'gemini-3.6-flash',
                'system_instruction' => 'Отвечай кратко',
                'generation_config' => [
                    'temperature' => 1.0,
                    'top_p' => 0.95,
                    'top_k' => 40,
                    'max_output_tokens' => 8192,
                    'candidate_count' => 1,
                    'stop_sequences' => [],
                    'seed' => null,
                    'presence_penalty' => null,
                    'frequency_penalty' => null,
                    'response_mime_type' => 'text/plain',
                    'thinking_config' => [
                        'thinking_budget' => 0,
                    ],
                ],
            ],
        ]);

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Скажи привет',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reply', 'Ответ модели')
            ->assertJsonPath('model', 'gemini-3.6-flash')
            ->assertJsonPath('message', null)
            ->assertJsonPath('usage.prompt_tokens', 12)
            ->assertJsonPath('usage.candidates_tokens', 4)
            ->assertJsonPath('usage.total_tokens', 16);

        Http::assertSent(function (Request $request): bool {
            $temperature = $request['generationConfig']['temperature'] ?? null;

            return $request->hasHeader('x-goog-api-key', 'valid-gemini-key')
                && str_contains($request->url(), '/v1beta/models/gemini-3.6-flash:generateContent')
                && ($request['contents'][0]['parts'][0]['text'] ?? null) === 'Скажи привет'
                && ($request['systemInstruction']['parts'][0]['text'] ?? null) === 'Отвечай кратко'
                && $temperature !== null
                && (float) $temperature === 1.0;
        });
    }

    public function test_generate_returns_gemini_error_message(): void
    {
        $apiMessage = 'This model models/gemini-2.5-flash is no longer available to new users.';

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => $apiMessage,
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create();

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Тест',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', $apiMessage)
            ->assertJsonPath('retryable', false)
            ->assertJsonPath('reply', null);
    }

    public function test_generate_marks_high_demand_error_as_retryable(): void
    {
        $apiMessage = 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.';

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => $apiMessage,
                    'status' => 'UNAVAILABLE',
                ],
            ], 503),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create();

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Тест',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', $apiMessage)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('reply', null);
    }

    public function test_generate_requires_prompt(): void
    {
        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create();

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_user_cannot_generate_for_another_users_service(): void
    {
        $owner = User::factory()->create();
        $service = AiService::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Привет',
        ])->assertForbidden();
    }

    public function test_user_can_generate_with_global_service(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse('Глобальный ответ'),
            ),
        ]);

        $this->actingAsAppUser();
        $service = AiService::factory()->global()->create([
            'api_key' => 'global-gemini-key',
            'settings' => [
                'model' => 'gemini-3.6-flash',
                'system_instruction' => null,
                'generation_config' => [
                    'temperature' => 1.0,
                    'top_p' => 0.95,
                    'top_k' => 40,
                    'max_output_tokens' => 8192,
                    'candidate_count' => 1,
                    'stop_sequences' => [],
                    'seed' => null,
                    'presence_penalty' => null,
                    'frequency_penalty' => null,
                    'response_mime_type' => 'text/plain',
                    'thinking_config' => [
                        'thinking_budget' => 0,
                    ],
                ],
            ],
        ]);

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Скажи привет',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reply', 'Глобальный ответ');
    }

    /**
     * @return array<string, mixed>
     */
    private function groqChatResponse(string $text = 'Привет!'): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => $text,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
    }

    public function test_user_can_generate_content_with_groq_service(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response(
                $this->groqChatResponse('Ответ Groq'),
            ),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->groq()->for($user)->create([
            'api_key' => 'valid-groq-key',
            'settings' => [
                'model' => 'openai/gpt-oss-120b',
                'system_instruction' => 'Отвечай кратко',
                'generation_config' => [
                    'temperature' => 0.5,
                    'top_p' => 0.9,
                    'max_output_tokens' => 1024,
                    'stop_sequences' => [],
                    'presence_penalty' => null,
                    'frequency_penalty' => null,
                ],
            ],
        ]);

        $this->postJson("/api/app/ai-services/{$service->id}/generate", [
            'prompt' => 'Скажи привет',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reply', 'Ответ Groq')
            ->assertJsonPath('model', 'openai/gpt-oss-120b')
            ->assertJsonPath('usage.prompt_tokens', 10)
            ->assertJsonPath('usage.candidates_tokens', 5)
            ->assertJsonPath('usage.total_tokens', 15);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer valid-groq-key')
                && str_contains($request->url(), '/chat/completions')
                && ($request['model'] ?? null) === 'openai/gpt-oss-120b'
                && ($request['messages'][0]['role'] ?? null) === 'system'
                && ($request['messages'][1]['content'] ?? null) === 'Скажи привет'
                && (float) ($request['temperature'] ?? -1) === 0.5
                && (int) ($request['max_tokens'] ?? 0) === 1024;
        });
    }
}
