<?php

namespace Tests\Feature;

use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGithubCommit;
use App\Models\SiteSearchConsoleDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportCommitReviewTest extends TestCase
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

    public function test_guest_cannot_review_commits(): void
    {
        $site = Site::factory()->create();

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/review-commits", [
            'ai_service_id' => 1,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'commit_ids' => [1],
        ])->assertUnauthorized();
    }

    public function test_user_cannot_review_commits_for_foreign_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $commit = SiteGithubCommit::factory()->for($site)->create([
            'files_fetched_at' => now(),
            'files' => [],
            'stats' => ['additions' => 0, 'deletions' => 0, 'total' => 0],
        ]);
        $attacker = $this->actingAsAppUser();
        $service = AiService::factory()->for($attacker)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/review-commits", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'commit_ids' => [$commit->id],
        ])->assertForbidden();
    }

    public function test_review_rejects_commits_without_files_or_foreign_commits(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $otherSite = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $withoutFiles = SiteGithubCommit::factory()->for($site)->create([
            'files_fetched_at' => null,
        ]);
        $foreign = SiteGithubCommit::factory()->for($otherSite)->create([
            'files_fetched_at' => now(),
            'files' => [
                [
                    'filename' => 'index.html',
                    'status' => 'modified',
                    'additions' => 1,
                    'deletions' => 0,
                    'patch' => '+title',
                ],
            ],
            'stats' => ['additions' => 1, 'deletions' => 0, 'total' => 1],
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/review-commits", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'commit_ids' => [$withoutFiles->id, $foreign->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['commit_ids.0', 'commit_ids.1']);
    }

    public function test_user_can_review_commits_with_search_console_context(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'name' => 'Demo Site',
            'url' => 'https://example.com',
        ]);
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteSearchConsoleDaily::factory()->for($site)->create([
            'date' => '2026-09-03',
            'clicks' => 5,
            'impressions' => 100,
            'ctr' => 0.05,
            'position' => 12.0,
        ]);

        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'message' => 'Change title tags',
            'author_date' => '2026-09-02 10:00:00',
            'files_fetched_at' => now(),
            'files' => [
                [
                    'filename' => 'resources/views/home.blade.php',
                    'status' => 'modified',
                    'additions' => 2,
                    'deletions' => 1,
                    'patch' => "-<title>Old</title>\n+<title>New</title>",
                ],
            ],
            'stats' => ['additions' => 2, 'deletions' => 1, 'total' => 3],
            'files_incomplete' => false,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse(
                    'Коммит aaaaaaa рядом с падением кликов 2026-09-03 мог снизить CTR из-за смены title.',
                ),
            ),
        ]);

        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/review-commits", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'commit_ids' => [$commit->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('commit_ids.0', $commit->id)
            ->assertJsonPath(
                'reply',
                'Коммит aaaaaaa рядом с падением кликов 2026-09-03 мог снизить CTR из-за смены title.',
            );

        Http::assertSent(function (Request $request) use ($commit): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $prompt = data_get($request->data(), 'contents.0.parts.0.text');

            return is_string($prompt)
                && str_contains($prompt, 'Search Console')
                && str_contains($prompt, '2026-09-03')
                && str_contains($prompt, (string) $commit->id)
                && str_contains($prompt, 'resources/views/home.blade.php')
                && str_contains($prompt, '<title>New</title>');
        });
    }

    public function test_generate_report_includes_commit_review_in_prompt_and_saved_prompt(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse("## Краткое резюме\nУчтено ревью коммитов."),
            ),
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'name' => 'Demo Site',
            'url' => 'https://example.com',
        ]);
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create([
            'date' => '2026-09-01',
            'sessions' => 100,
            'organic_sessions' => 40,
        ]);
        SiteSearchConsoleDaily::factory()->for($site)->create([
            'date' => '2026-09-01',
            'clicks' => 20,
            'impressions' => 400,
        ]);

        $commitReview = 'Предварительное ревью: коммит aaaaaaa совпал с падением CTR 2026-09-03.';

        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'commit_review' => $commitReview,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true);

        $saved = $site->aiReports()->first();
        $this->assertNotNull($saved);
        $this->assertStringContainsString('Предварительное ревью коммитов (Search Console)', (string) $saved->prompt);
        $this->assertStringContainsString($commitReview, (string) $saved->prompt);

        Http::assertSent(function (Request $request) use ($commitReview): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $prompt = data_get($request->data(), 'contents.0.parts.0.text');

            return is_string($prompt)
                && str_contains($prompt, 'Предварительное ревью коммитов (Search Console)')
                && str_contains($prompt, $commitReview);
        });
    }
}
