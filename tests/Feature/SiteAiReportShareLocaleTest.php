<?php

namespace Tests\Feature;

use App\Enums\AiReportVisibility;
use App\Models\Site;
use App\Models\SiteAiReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportShareLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_share_url_uses_russian_prefix_for_russian_report(): void
    {
        $user = $this->actingAsAppUser(User::factory()->create(['locale' => 'ru']));
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->create(['locale' => 'ru']);

        $response = $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Link->value,
        ])->assertOk();

        $report->refresh();
        $shareUrl = $response->json('data.sharing.share_url');

        $this->assertSame($shareUrl, $report->shareUrl());
        $this->assertStringContainsString('/ru/r/'.$report->share_token, (string) $shareUrl);
        $this->assertStringNotContainsString('/en/r/', (string) $shareUrl);

        $this->get('/ru/r/'.$report->share_token)
            ->assertOk()
            ->assertSee(__('public.shared_report.eyebrow', [], 'ru'), false);
    }

    public function test_share_url_omits_prefix_for_default_english_report(): void
    {
        $user = $this->actingAsAppUser(User::factory()->create(['locale' => 'en']));
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->create(['locale' => 'en']);

        $response = $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Link->value,
        ])->assertOk();

        $report->refresh();
        $shareUrl = $response->json('data.sharing.share_url');

        $this->assertSame($shareUrl, $report->shareUrl());
        $this->assertMatchesRegularExpression('#https?://[^/]+/r/'.$report->share_token.'$#', (string) $shareUrl);
        $this->assertStringNotContainsString('/ru/r/', (string) $shareUrl);

        $this->get('/r/'.$report->share_token)
            ->assertOk()
            ->assertSee(__('public.shared_report.eyebrow', [], 'en'), false);
    }

    public function test_password_unlock_keeps_locale_prefix(): void
    {
        $report = SiteAiReport::factory()->passwordProtected('correct-pass')->create([
            'locale' => 'ru',
            'reply' => 'Секретный отчёт',
        ]);

        $this->from('/ru/r/'.$report->share_token)
            ->post('/ru/r/'.$report->share_token.'/unlock', [
                'password' => 'correct-pass',
            ])
            ->assertRedirect('/ru/r/'.$report->share_token);

        $this->get('/ru/r/'.$report->share_token)
            ->assertOk()
            ->assertViewIs('public.ai-reports.show');
    }
}
