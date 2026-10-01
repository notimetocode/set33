<?php

namespace Tests\Feature;

use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteGithubCommit;
use App\Models\SitePageSpeedLabSnapshot;
use App\Models\SiteUrlInspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportPreprocessApplyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_apply_preprocess(): void
    {
        $site = Site::factory()->create();

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/apply", [
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'items' => [
                [
                    'key' => 'gsc_dimensions',
                    'type' => 'gsc_dimensions',
                ],
            ],
        ])->assertUnauthorized();
    }

    public function test_apply_marks_lab_details_flag(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create(['url' => 'https://example.com']);
        $snapshot = SitePageSpeedLabSnapshot::factory()->for($site)->create([
            'include_details_in_report' => false,
            'payload' => ['lighthouseResult' => ['audits' => [], 'categories' => []]],
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/apply", [
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'items' => [
                [
                    'key' => 'pagespeed_lab_details:'.$snapshot->id,
                    'type' => 'pagespeed_lab_details',
                    'snapshot_id' => $snapshot->id,
                    'reason' => 'Need opportunities',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('results.0.ok', true);

        $this->assertTrue($snapshot->fresh()->include_details_in_report);
    }

    public function test_apply_upserts_url_inspection_without_wiping_others(): void
    {
        Http::preventStrayRequests();

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create(['url' => 'https://example.com']);

        $existing = SiteUrlInspection::factory()->for($site)->create([
            'inspected_url' => 'https://example.com/keep',
            'verdict' => 'PASS',
        ]);

        // Without Google integration this should fail gracefully per item.
        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess/apply", [
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'items' => [
                [
                    'key' => 'gsc_url_inspection:https://example.com/new',
                    'type' => 'gsc_url_inspection',
                    'url' => 'https://example.com/new',
                    'reason' => 'Check indexing',
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('ok', false);
        $this->assertDatabaseHas('site_url_inspections', [
            'id' => $existing->id,
            'inspected_url' => 'https://example.com/keep',
        ]);
    }

    public function test_preprocess_returns_typed_keys_for_commits(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create(['url' => 'https://example.com']);
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => [
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
                    'thinking_config' => ['thinking_budget' => 0],
                ],
            ],
        ]);

        SiteAnalyticsDaily::factory()->for($site)->create(['date' => '2026-09-01']);

        $commit = SiteGithubCommit::factory()->for($site)->create([
            'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'message' => 'SEO deploy',
            'author_date' => '2026-09-03 10:00:00',
            'files_fetched_at' => null,
        ]);

        $reply = json_encode([
            'items' => [
                [
                    'type' => 'github_commit_files',
                    'commit_id' => $commit->id,
                    'sha' => $commit->sha,
                    'reason' => 'Deploy',
                ],
                [
                    'type' => 'gsc_dimensions',
                    'reason' => 'Need dims',
                ],
                [
                    'type' => 'unknown_type',
                    'reason' => 'skip',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => $reply]],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 5,
                    'totalTokenCount' => 15,
                ],
            ]),
        ]);

        $response = $this->postJson("/api/app/sites/{$site->id}/ai-reports/preprocess", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('items.0.key', 'github_commit_files:'.$commit->id)
            ->assertJsonPath('items.1.key', 'gsc_dimensions');

        $this->assertCount(2, $response->json('items'));
    }
}
