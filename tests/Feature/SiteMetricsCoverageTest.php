<?php

namespace Tests\Feature;

use App\Enums\SearchConsoleDimension;
use App\Models\Site;
use App\Models\SiteAnalyticsDaily;
use App\Models\SiteCruxSnapshot;
use App\Models\SiteGithubCommit;
use App\Models\SitePageSpeedLabSnapshot;
use App\Models\SiteSearchConsoleDaily;
use App\Models\SiteSearchConsoleDimension;
use App\Models\SiteUrlInspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteMetricsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_view_metrics_coverage(): void
    {
        $site = Site::factory()->create();

        $this->getJson("/api/app/sites/{$site->id}/metrics/coverage")
            ->assertUnauthorized();
    }

    public function test_user_cannot_view_coverage_for_foreign_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();

        $this->actingAsAppUser();

        $this->getJson("/api/app/sites/{$site->id}/metrics/coverage")
            ->assertForbidden();
    }

    public function test_coverage_returns_null_ranges_when_no_data(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->getJson("/api/app/sites/{$site->id}/metrics/coverage")
            ->assertOk()
            ->assertJsonPath('data.analytics', null)
            ->assertJsonPath('data.search_console_daily', null)
            ->assertJsonPath('data.search_console_dimensions', null)
            ->assertJsonPath('data.github_commits', null)
            ->assertJsonPath('data.pagespeed_lab', null)
            ->assertJsonPath('data.crux', null);
    }

    public function test_coverage_returns_stored_data_periods(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteAnalyticsDaily::factory()->for($site)->create(['date' => '2026-09-01']);
        SiteAnalyticsDaily::factory()->for($site)->create(['date' => '2026-09-10']);

        SiteSearchConsoleDaily::factory()->for($site)->create(['date' => '2026-09-02']);
        SiteSearchConsoleDaily::factory()->for($site)->create(['date' => '2026-09-08']);

        SiteSearchConsoleDimension::factory()->for($site)->create([
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-07',
            'dimension' => SearchConsoleDimension::Query,
            'value' => 'купить велосипед',
            'rank' => 1,
        ]);
        SiteUrlInspection::factory()->for($site)->create([
            'period_from' => '2026-08-20',
            'period_to' => '2026-08-26',
            'inspected_url' => 'https://example.com/',
        ]);

        SiteGithubCommit::factory()->for($site)->create([
            'author_date' => '2026-09-03 12:00:00',
        ]);
        SiteGithubCommit::factory()->for($site)->create([
            'author_date' => '2026-09-12 18:30:00',
        ]);

        SitePageSpeedLabSnapshot::factory()->for($site)->create([
            'fetched_at' => '2026-09-05 10:00:00',
        ]);
        SitePageSpeedLabSnapshot::factory()->for($site)->create([
            'fetched_at' => '2026-09-15 11:00:00',
        ]);

        SiteCruxSnapshot::factory()->for($site)->create([
            'collection_period_start' => '2026-08-01',
            'collection_period_end' => '2026-08-28',
            'fetched_at' => '2026-09-01 09:00:00',
        ]);

        $this->getJson("/api/app/sites/{$site->id}/metrics/coverage")
            ->assertOk()
            ->assertJsonPath('data.analytics.from', '2026-09-01')
            ->assertJsonPath('data.analytics.to', '2026-09-10')
            ->assertJsonPath('data.search_console_daily.from', '2026-09-02')
            ->assertJsonPath('data.search_console_daily.to', '2026-09-08')
            ->assertJsonPath('data.search_console_dimensions.from', '2026-08-20')
            ->assertJsonPath('data.search_console_dimensions.to', '2026-09-07')
            ->assertJsonPath('data.github_commits.from', '2026-09-03')
            ->assertJsonPath('data.github_commits.to', '2026-09-12')
            ->assertJsonPath('data.pagespeed_lab.from', '2026-09-05')
            ->assertJsonPath('data.pagespeed_lab.to', '2026-09-15')
            ->assertJsonPath('data.crux.from', '2026-08-01')
            ->assertJsonPath('data.crux.to', '2026-08-28');
    }
}
