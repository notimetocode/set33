<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAppUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['app']);

        return $user;
    }

    private function markdownUpload(string $name = 'guide.md', string $content = "# Guide\n\nContent"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_guest_cannot_manage_documents(): void
    {
        $site = Site::factory()->create();

        $this->getJson("/api/app/sites/{$site->id}/documents")
            ->assertUnauthorized();

        $this->post("/api/app/sites/{$site->id}/documents", [
            'title' => 'Гайд',
            'document' => $this->markdownUpload(),
        ], [
            'Accept' => 'application/json',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_manage_foreign_site_documents(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner)->create();
        $document = SiteDocument::factory()->for($site)->create([
            'title' => 'Чужой документ',
        ]);
        $this->actingAsAppUser();

        $this->getJson("/api/app/sites/{$site->id}/documents")
            ->assertForbidden();

        $this->post("/api/app/sites/{$site->id}/documents", [
            'title' => 'Гайд',
            'document' => $this->markdownUpload(),
        ])->assertForbidden();

        $this->post("/api/app/sites/{$site->id}/documents/{$document->id}", [
            'title' => 'Изменено',
        ])->assertForbidden();

        $this->deleteJson("/api/app/sites/{$site->id}/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_user_can_list_create_update_and_delete_documents(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        SiteDocument::factory()->for($site)->create([
            'title' => 'Старый документ',
            'description' => 'Описание',
            'content' => "# Old\n\nText",
            'original_filename' => 'old.md',
        ]);

        $this->getJson("/api/app/sites/{$site->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Старый документ')
            ->assertJsonPath('data.0.original_filename', 'old.md');

        $created = $this->post("/api/app/sites/{$site->id}/documents", [
            'title' => 'Брендбук',
            'description' => 'Тон и голос бренда',
            'document' => $this->markdownUpload('brand.md', "# Brand\n\nFriendly tone"),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Брендбук')
            ->assertJsonPath('data.description', 'Тон и голос бренда')
            ->assertJsonPath('data.original_filename', 'brand.md')
            ->json('data');

        $this->assertDatabaseHas('site_documents', [
            'id' => $created['id'],
            'site_id' => $site->id,
            'title' => 'Брендбук',
            'original_filename' => 'brand.md',
            'content' => "# Brand\n\nFriendly tone",
        ]);

        $this->post("/api/app/sites/{$site->id}/documents/{$created['id']}", [
            'title' => 'Обновлённый брендбук',
            'description' => '',
            'document' => $this->markdownUpload('brand-v2.md', "# Brand v2\n\nUpdated"),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Обновлённый брендбук')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.original_filename', 'brand-v2.md');

        $this->assertDatabaseHas('site_documents', [
            'id' => $created['id'],
            'title' => 'Обновлённый брендбук',
            'content' => "# Brand v2\n\nUpdated",
        ]);

        $this->deleteJson("/api/app/sites/{$site->id}/documents/{$created['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('site_documents', [
            'id' => $created['id'],
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();

        $this->post("/api/app/sites/{$site->id}/documents", [
            'title' => '',
            'document' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'document']);
    }

    public function test_update_returns_404_for_document_from_another_site(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $otherSite = Site::factory()->for($user)->create();
        $document = SiteDocument::factory()->for($otherSite)->create([
            'title' => 'Чужой',
        ]);

        $this->post("/api/app/sites/{$site->id}/documents/{$document->id}", [
            'title' => 'Попытка',
        ])->assertNotFound();

        $this->deleteJson("/api/app/sites/{$site->id}/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_update_can_change_metadata_without_new_file(): void
    {
        $user = $this->actingAsAppUser();
        $site = Site::factory()->for($user)->create();
        $document = SiteDocument::factory()->for($site)->create([
            'title' => 'Исходный',
            'description' => 'Было',
            'content' => "# Keep\n\nSame content",
            'original_filename' => 'keep.md',
        ]);

        $this->post("/api/app/sites/{$site->id}/documents/{$document->id}", [
            'title' => 'Новое название',
            'description' => 'Новое описание',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Новое название')
            ->assertJsonPath('data.description', 'Новое описание')
            ->assertJsonPath('data.original_filename', 'keep.md');

        $this->assertDatabaseHas('site_documents', [
            'id' => $document->id,
            'title' => 'Новое название',
            'content' => "# Keep\n\nSame content",
            'original_filename' => 'keep.md',
        ]);
    }
}
