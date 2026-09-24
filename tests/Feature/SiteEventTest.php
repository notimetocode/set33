<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteEventTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    public function test_guest_cannot_manage_events(): void
    {
        $site = Site::factory()->create();

        $this->getJson("/api/app/sites/{$site->id}/events?from=2026-09-01&to=2026-09-07")
            ->assertUnauthorized();

        $this->postJson("/api/app/sites/{$site->id}/events", [
            'occurred_on' => '2026-09-03',
            'title' => 'Статья на Onliner',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_manage_foreign_site_events(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $event = SiteEvent::factory()->for($site)->create([
            'occurred_on' => '2026-09-03',
            'title' => 'Чужое событие',
        ]);
        $this->actingAsAppUser();

        $this->getJson("/api/app/sites/{$site->id}/events?from=2026-09-01&to=2026-09-07")
            ->assertForbidden();

        $this->postJson("/api/app/sites/{$site->id}/events", [
            'occurred_on' => '2026-09-03',
            'title' => 'Статья на Onliner',
        ])->assertForbidden();

        $this->putJson("/api/app/sites/{$site->id}/events/{$event->id}", [
            'occurred_on' => '2026-09-03',
            'title' => 'Изменено',
        ])->assertForbidden();

        $this->deleteJson("/api/app/sites/{$site->id}/events/{$event->id}")
            ->assertForbidden();
    }

    public function test_user_can_list_create_update_and_delete_events(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteEvent::factory()->for($site)->create([
            'occurred_on' => '2026-08-20',
            'title' => 'Вне периода',
        ]);
        $inPeriod = SiteEvent::factory()->for($site)->create([
            'occurred_on' => '2026-09-02',
            'title' => 'Старое событие',
            'description' => 'Описание',
            'url' => 'https://example.com/old',
        ]);

        $this->getJson("/api/app/sites/{$site->id}/events?from=2026-09-01&to=2026-09-07")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inPeriod->id)
            ->assertJsonPath('data.0.title', 'Старое событие');

        $created = $this->postJson("/api/app/sites/{$site->id}/events", [
            'occurred_on' => '2026-09-03',
            'title' => 'На портале Onliner вышла статья про наш сайт',
            'description' => 'Упоминание в обзоре сервисов',
            'url' => 'https://onliner.by/article',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'На портале Onliner вышла статья про наш сайт')
            ->assertJsonPath('data.occurred_on', '2026-09-03')
            ->json('data');

        $this->assertDatabaseHas('site_events', [
            'id' => $created['id'],
            'site_id' => $site->id,
            'title' => 'На портале Onliner вышла статья про наш сайт',
            'url' => 'https://onliner.by/article',
        ]);

        $this->putJson("/api/app/sites/{$site->id}/events/{$created['id']}", [
            'occurred_on' => '2026-09-04',
            'title' => 'Обновлённое событие',
            'description' => null,
            'url' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Обновлённое событие')
            ->assertJsonPath('data.occurred_on', '2026-09-04')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.url', null);

        $this->deleteJson("/api/app/sites/{$site->id}/events/{$created['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('site_events', [
            'id' => $created['id'],
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->postJson("/api/app/sites/{$site->id}/events", [
            'occurred_on' => '',
            'title' => '',
            'url' => 'not-a-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['occurred_on', 'title', 'url']);
    }

    public function test_update_returns_404_for_event_from_another_site(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $otherSite = Site::factory()->for($user)->create();
        $event = SiteEvent::factory()->for($otherSite)->create([
            'occurred_on' => '2026-09-03',
            'title' => 'Чужое',
        ]);

        $this->putJson("/api/app/sites/{$site->id}/events/{$event->id}", [
            'occurred_on' => '2026-09-03',
            'title' => 'Попытка',
        ])->assertNotFound();

        $this->deleteJson("/api/app/sites/{$site->id}/events/{$event->id}")
            ->assertNotFound();
    }
}
