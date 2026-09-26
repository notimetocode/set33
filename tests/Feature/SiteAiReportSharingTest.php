<?php

namespace Tests\Feature;

use App\Enums\AiReportVisibility;
use App\Models\Site;
use App\Models\SiteAiReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAiReportSharingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_owner_can_share_report_by_link(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->create();

        $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Link->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.sharing.visibility', 'link')
            ->assertJsonPath('data.sharing.has_password', false)
            ->assertJsonStructure(['data' => ['sharing' => ['share_url']]]);

        $report->refresh();

        $this->assertSame(AiReportVisibility::Link, $report->visibility);
        $this->assertNotNull($report->share_token);
        $this->assertNull($report->share_password);
        $this->assertStringContainsString('/r/'.$report->share_token, $report->shareUrl());
    }

    public function test_owner_can_share_report_with_password(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->create();

        $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Password->value,
            'password' => 'secret12',
        ])
            ->assertOk()
            ->assertJsonPath('data.sharing.visibility', 'password')
            ->assertJsonPath('data.sharing.has_password', true);

        $report->refresh();

        $this->assertSame(AiReportVisibility::Password, $report->visibility);
        $this->assertNotNull($report->share_token);
        $this->assertNotNull($report->share_password);
    }

    public function test_password_is_required_when_enabling_password_access(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->create();

        $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Password->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_owner_can_make_shared_report_private(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->passwordProtected()->create();
        $token = $report->share_token;

        $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Private->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.sharing.visibility', 'private')
            ->assertJsonPath('data.sharing.share_url', null);

        $report->refresh();

        $this->assertSame(AiReportVisibility::Private, $report->visibility);
        $this->assertNull($report->share_token);
        $this->assertNull($report->share_password);

        $this->get('/r/'.$token)->assertNotFound();
    }

    public function test_foreign_user_cannot_update_sharing(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $report = SiteAiReport::factory()->for($site)->create();

        $this->actingAsAppUser();

        $this->putJson("/api/app/sites/{$site->id}/ai-reports/{$report->id}/sharing", [
            'visibility' => AiReportVisibility::Link->value,
        ])->assertForbidden();
    }

    public function test_public_link_shows_shared_report(): void
    {
        $report = SiteAiReport::factory()->shared()->create([
            'reply' => "## Краткое резюме\nПубличный отчёт",
        ]);

        $this->get('/r/'.$report->share_token)
            ->assertOk()
            ->assertViewIs('public.ai-reports.show')
            ->assertViewHas('payload', function (array $payload) use ($report): bool {
                return ($payload['reply'] ?? null) === $report->reply
                    && ($payload['site']['name'] ?? null) === $report->site->name;
            })
            ->assertSee('shared-ai-report-data', false);
    }

    public function test_private_report_token_is_not_publicly_accessible(): void
    {
        $report = SiteAiReport::factory()->create([
            'share_token' => 'privateTokenShouldNotWork1234567890',
            'visibility' => AiReportVisibility::Private,
        ]);

        $this->get('/r/'.$report->share_token)->assertNotFound();
    }

    public function test_password_protected_report_requires_unlock(): void
    {
        $report = SiteAiReport::factory()->passwordProtected('correct-pass')->create([
            'reply' => 'Секретный отчёт',
        ]);

        $this->get('/r/'.$report->share_token)
            ->assertOk()
            ->assertSee('Отчёт защищён паролем')
            ->assertDontSee('Секретный отчёт');

        $this->from('/r/'.$report->share_token)
            ->post('/r/'.$report->share_token.'/unlock', [
                'password' => 'wrong-pass',
            ])
            ->assertRedirect('/r/'.$report->share_token)
            ->assertSessionHasErrors('password');

        $this->from('/r/'.$report->share_token)
            ->post('/r/'.$report->share_token.'/unlock', [
                'password' => 'correct-pass',
            ])
            ->assertRedirect('/r/'.$report->share_token);

        $this->get('/r/'.$report->share_token)
            ->assertOk()
            ->assertViewIs('public.ai-reports.show')
            ->assertViewHas('payload', function (array $payload): bool {
                return ($payload['reply'] ?? null) === 'Секретный отчёт';
            });
    }

    public function test_list_includes_sharing_summary(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $report = SiteAiReport::factory()->for($site)->shared()->create();

        $this->getJson("/api/app/sites/{$site->id}/ai-reports")
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.sharing.visibility', 'link')
            ->assertJsonMissingPath('data.0.reply');
    }
}
