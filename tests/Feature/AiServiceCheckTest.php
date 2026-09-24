<?php

namespace Tests\Feature;

use App\Enums\AiServiceStatus;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiServiceCheckTest extends TestCase
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
    private function generateContentResponse(string $text = 'OK'): array
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
        ];
    }

    public function test_guest_cannot_check_ai_service(): void
    {
        $service = AiService::factory()->create();

        $this->postJson("/api/app/ai-services/{$service->id}/check")
            ->assertUnauthorized();
    }

    public function test_user_can_check_own_ai_service_successfully(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse('OK'),
            ),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-gemini-key',
            'status' => AiServiceStatus::Unchecked,
        ]);

        $this->postJson("/api/app/ai-services/{$service->id}/check")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('title', 'Проверка успешна')
            ->assertJsonPath('reply', 'OK')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.status_label', 'Работает')
            ->assertJsonPath('data.status_message', 'Подключение к Gemini работает.');

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('x-goog-api-key', 'valid-gemini-key')
                && str_contains($request->url(), '/v1beta/models/gemini-3.6-flash:generateContent')
                && ($request['contents'][0]['parts'][0]['text'] ?? null) === 'Ответь одним словом: OK';
        });

        $service->refresh();
        $this->assertSame(AiServiceStatus::Ok, $service->status);
        $this->assertNotNull($service->status_checked_at);
    }

    public function test_check_marks_error_when_gemini_rejects_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'error' => [
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $user = $this->actingAsAppUser();
        $service = AiService::factory()->for($user)->create([
            'status' => AiServiceStatus::Ok,
        ]);

        $this->postJson("/api/app/ai-services/{$service->id}/check")
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('title', 'Проверка не пройдена')
            ->assertJsonPath('data.status', 'error')
            ->assertJsonPath('data.status_label', 'Ошибка')
            ->assertJsonPath('data.status_message', 'API key not valid. Please pass a valid API key.');

        $this->assertSame(AiServiceStatus::Error, $service->fresh()->status);
    }

    public function test_check_shows_gemini_error_message_for_unavailable_model(): void
    {
        $apiMessage = 'This model models/gemini-2.5-flash is no longer available to new users. Please update your code to use a newer model for the latest features and improvements.';

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

        $this->postJson("/api/app/ai-services/{$service->id}/check")
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', $apiMessage)
            ->assertJsonPath('data.status', 'error')
            ->assertJsonPath('data.status_message', $apiMessage);
    }

    public function test_user_cannot_check_another_users_ai_service(): void
    {
        $owner = User::factory()->create();
        $service = AiService::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->postJson("/api/app/ai-services/{$service->id}/check")
            ->assertForbidden();
    }
}
