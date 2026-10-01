<?php

namespace Tests\Feature;

use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGithubCommit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportPreprocessTest extends TestCase
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
    private function generateContentResponse(string $text): array
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
                'promptTokenCount' => 80,
                'candidatesTokenCount' => 30,
                'totalTokenCount' => 110,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiSettings(): array
    {
        return [
            'model' => 'gemini-3.6-flash',
            'system_instruction' => 'Отвечай на русском',
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
        ];
    }

    public function test_guest_cannot_preprocess_ai_report(): void
    {
        $site = Site::factory()->create();

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => 1,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_preprocess_report_for_foreign_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $attacker = $this->actingAsAppUser();
        $service = AiService::factory()->for($attacker)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertForbidden();
    }

    public function test_user_cannot_use_foreign_ai_service_for_preprocess(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $foreignService = AiService::factory()->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create([
            'date' => '2026-09-01',
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $foreignService->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ai_service_id']);
    }

    public function test_preprocess_returns_only_valid_commits_without_files(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create([
            'date' => '2026-09-01',
        ]);

        $eligible = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'message' => 'Refactor SEO templates',
            'author_date' => '2026-09-03 10:00:00',
            'files_fetched_at' => null,
        ]);

        $alreadyFetched = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'message' => 'Already fetched',
            'author_date' => '2026-09-04 10:00:00',
            'files_fetched_at' => now(),
            'files' => [],
            'stats' => ['additions' => 1, 'deletions' => 0, 'total' => 1],
        ]);

        $outsidePeriod = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'cccccccccccccccccccccccccccccccccccccccc',
            'message' => 'Outside period',
            'author_date' => '2026-08-01 10:00:00',
            'files_fetched_at' => null,
        ]);

        $reply = json_encode([
            'items' => [
                [
                    'type' => 'github_commit_files',
                    'commit_id' => $eligible->id,
                    'sha' => $eligible->sha,
                    'reason' => 'Крупный SEO-рефакторинг рядом с просадкой CTR',
                ],
                [
                    'type' => 'github_commit_files',
                    'commit_id' => $alreadyFetched->id,
                    'sha' => $alreadyFetched->sha,
                    'reason' => 'Уже выгружен',
                ],
                [
                    'type' => 'github_commit_files',
                    'commit_id' => $outsidePeriod->id,
                    'sha' => $outsidePeriod->sha,
                    'reason' => 'Вне периода',
                ],
                [
                    'type' => 'analytics_extra',
                    'commit_id' => $eligible->id,
                    'sha' => $eligible->sha,
                    'reason' => 'Будущий тип',
                ],
                [
                    'type' => 'github_commit_files',
                    'commit_id' => 999999,
                    'sha' => 'dddddddddddddddddddddddddddddddddddddddd',
                    'reason' => 'Чужой коммит',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse((string) $reply),
            ),
        ]);

        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('items.0.type', 'github_commit_files')
            ->assertJsonPath('items.0.key', 'github_commit_files:'.$eligible->id)
            ->assertJsonPath('items.0.commit_id', $eligible->id)
            ->assertJsonPath('items.0.sha', $eligible->sha)
            ->assertJsonPath('items.0.short_sha', 'aaaaaaa')
            ->assertJsonPath('items.0.reason', 'Крупный SEO-рефакторинг рядом с просадкой CTR');

        $this->assertCount(1, $response->json('items'));
    }

    public function test_preprocess_fails_on_invalid_json_reply(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create([
            'date' => '2026-09-01',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse('Это не JSON'),
            ),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('items', [])
            ->assertJsonPath('retryable', false);
    }

    public function test_preprocess_parses_fenced_json_and_filters_unknown_types(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create([
            'date' => '2026-09-02',
        ]);

        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
            'message' => 'Fix canonical URLs',
            'author_date' => '2026-09-02 12:00:00',
            'files_fetched_at' => null,
        ]);

        $fenced = <<<TXT
Вот рекомендации:

```json
{
  "items": [
    {
      "type": "github_commit_files",
      "commit_id": {$commit->id},
      "sha": "{$commit->sha}",
      "reason": "Правка canonical"
    },
    {
      "type": "search_console_extra",
      "reason": "Позже"
    }
  ]
}
```
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse($fenced),
            ),
        ]);

        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.commit_id', $commit->id)
            ->assertJsonPath('items.0.reason', 'Правка canonical');
    }

    public function test_preprocess_fails_when_no_period_data(): void
    {
        Http::preventStrayRequests();

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('items', [])
            ->assertJsonPath('retryable', false);

        Http::assertNothingSent();
    }
}
