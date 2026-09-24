<?php

namespace Tests\Feature;

use App\Enums\SearchConsoleDimension;
use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAiReport;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteEvent;
use App\Models\SiteGithubCommit;
use App\Models\SiteSearchConsoleDaily;
use App\Models\SiteSearchConsoleDimension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportTest extends TestCase
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
    private function generateContentResponse(string $text = 'SEO-отчёт'): array
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
                'promptTokenCount' => 120,
                'candidatesTokenCount' => 40,
                'totalTokenCount' => 160,
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

    public function test_guest_cannot_generate_ai_report(): void
    {
        $site = Site::factory()->create();

        $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => 1,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_generate_report_for_foreign_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $attacker = $this->actingAsAppUser();
        $service = AiService::factory()->for($attacker)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertForbidden();
    }

    public function test_user_cannot_use_foreign_ai_service(): void
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

        $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => $foreignService->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ai_service_id']);
    }

    public function test_generation_fails_when_no_service_data(): void
    {
        Http::preventStrayRequests();

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'api_key' => 'valid-key',
            'settings' => $this->geminiSettings(),
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('data', null)
            ->assertJsonFragment([
                'analytics' => 0,
                'search_console' => 0,
                'search_console_queries' => 0,
                'search_console_pages' => 0,
                'search_console_devices' => 0,
                'search_console_countries' => 0,
                'github_commits' => 0,
                'events' => 0,
            ]);

        $this->assertDatabaseCount('site_ai_reports', 0);
        Http::assertNothingSent();
    }

    public function test_user_can_generate_and_persist_ai_report(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
                $this->generateContentResponse("## Краткое резюме\nРост органики."),
            ),
        ]);

        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create([
            'name' => 'Demo Site',
            'url' => 'https://example.com',
        ]);
        $service = AiService::factory()->for($user)->create([
            'name' => 'Google Gemini · gemini-3.6-flash',
            'api_key' => 'valid-gemini-key',
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
        SiteSearchConsoleDimension::factory()->for($site)->create([
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Query,
            'value' => 'купить велосипед',
            'rank' => 1,
            'clicks' => 12,
            'impressions' => 200,
            'ctr' => 0.06,
            'position' => 3.5,
        ]);
        SiteSearchConsoleDimension::factory()->for($site)->create([
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Page,
            'value' => 'https://example.com/bikes',
            'rank' => 1,
            'clicks' => 15,
            'impressions' => 220,
            'ctr' => 0.068,
            'position' => 4.1,
        ]);
        SiteSearchConsoleDimension::factory()->for($site)->create([
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Device,
            'value' => 'MOBILE',
            'rank' => 1,
            'clicks' => 14,
            'impressions' => 300,
            'ctr' => 0.046,
            'position' => 8.2,
        ]);
        SiteSearchConsoleDimension::factory()->for($site)->create([
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Country,
            'value' => 'rus',
            'rank' => 1,
            'clicks' => 18,
            'impressions' => 350,
            'ctr' => 0.051,
            'position' => 7.0,
        ]);
        SiteGithubCommit::factory()->for($site)->create([
            'message' => "Improve SEO meta\n\nDetails",
            'author_date' => '2026-09-02 10:00:00',
        ]);
        SiteEvent::factory()->for($site)->create([
            'occurred_on' => '2026-09-03',
            'title' => 'На портале Onliner вышла статья про наш сайт',
            'description' => 'Упоминание в обзоре',
            'url' => 'https://onliner.by/article',
        ]);

        $this->postJson("/api/app/sites/{$site->id}/ai-reports", [
            'ai_service_id' => $service->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.reply', "## Краткое резюме\nРост органики.")
            ->assertJsonPath('data.tool.name', 'Google Gemini · gemini-3.6-flash')
            ->assertJsonPath('data.tool.model', 'gemini-3.6-flash')
            ->assertJsonPath('data.period.from', '2026-09-01')
            ->assertJsonPath('data.period.to', '2026-09-07')
            ->assertJsonPath('data_counts.analytics', 1)
            ->assertJsonPath('data_counts.search_console', 1)
            ->assertJsonPath('data_counts.search_console_queries', 1)
            ->assertJsonPath('data_counts.search_console_pages', 1)
            ->assertJsonPath('data_counts.search_console_devices', 1)
            ->assertJsonPath('data_counts.search_console_countries', 1)
            ->assertJsonPath('data_counts.github_commits', 1)
            ->assertJsonPath('data_counts.events', 1);

        $this->assertDatabaseHas('site_ai_reports', [
            'site_id' => $site->id,
            'ai_service_id' => $service->id,
            'ai_service_name' => 'Google Gemini · gemini-3.6-flash',
            'model' => 'gemini-3.6-flash',
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
        ]);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $prompt = data_get($request->data(), 'contents.0.parts.0.text');

            return is_string($prompt)
                && str_contains($prompt, 'сформируй SEO-отчёт')
                && str_contains($prompt, 'Demo Site')
                && str_contains($prompt, 'https://example.com')
                && str_contains($prompt, 'Google Analytics 4')
                && str_contains($prompt, 'Google Search Console')
                && str_contains($prompt, 'Топ-запросы')
                && str_contains($prompt, 'купить велосипед')
                && str_contains($prompt, 'Топ-страницы')
                && str_contains($prompt, 'https://example.com/bikes')
                && str_contains($prompt, 'Устройства')
                && str_contains($prompt, 'MOBILE')
                && str_contains($prompt, 'Страны')
                && str_contains($prompt, 'rus')
                && str_contains($prompt, 'Improve SEO meta')
                && str_contains($prompt, '"organic_sessions":40')
                && str_contains($prompt, 'На портале Onliner вышла статья про наш сайт')
                && str_contains($prompt, '## События');
        });
    }

    public function test_user_can_list_and_show_saved_reports(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $service = AiService::factory()->for($user)->create([
            'name' => 'Google Gemini · gemini-3.6-flash',
            'settings' => $this->geminiSettings(),
        ]);

        $older = SiteAiReport::factory()->for($site)->create([
            'ai_service_id' => $service->id,
            'ai_service_name' => 'Google Gemini · gemini-3.6-flash',
            'model' => 'gemini-3.6-flash',
            'reply' => 'Старый отчёт',
            'created_at' => now()->subDay(),
        ]);
        $newer = SiteAiReport::factory()->for($site)->create([
            'ai_service_id' => $service->id,
            'ai_service_name' => 'Google Gemini · gemini-2.0-flash',
            'model' => 'gemini-2.0-flash',
            'reply' => 'Новый отчёт',
            'created_at' => now(),
        ]);

        $this->getJson("/api/app/sites/{$site->id}/ai-reports")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.tool.model', 'gemini-2.0-flash')
            ->assertJsonMissingPath('data.0.reply')
            ->assertJsonPath('data.1.id', $older->id);

        $this->getJson("/api/app/sites/{$site->id}/ai-reports/{$newer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $newer->id)
            ->assertJsonPath('data.reply', 'Новый отчёт')
            ->assertJsonPath('data.tool.label', 'Google Gemini · gemini-2.0-flash');
    }

    public function test_user_cannot_view_foreign_site_reports(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $report = SiteAiReport::factory()->for($site)->create();

        $this->actingAsAppUser();

        $this->getJson("/api/app/sites/{$site->id}/ai-reports")->assertForbidden();
        $this->getJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}")->assertForbidden();
    }

    public function test_show_returns_404_for_report_from_another_site(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $otherSite = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($otherSite)->create();

        $this->getJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}")
            ->assertNotFound();
    }
}
