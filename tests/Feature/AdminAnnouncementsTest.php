<?php

namespace Tests\Feature;

use App\Livewire\Admin\AnnouncementEditor;
use App\Models\FeatureAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.announcements'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.announcements'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_open_the_editor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.announcements'))->assertOk();
    }

    public function test_an_admin_can_create_a_draft_announcement(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Neu: Wochenplan')
            ->set('formDescription', 'Plane deine wiederkehrende Woche an einem Ort.')
            ->set('formRelatedModule', 'weekplan')
            ->call('save');

        $announcement = FeatureAnnouncement::sole();
        $this->assertSame('Neu: Wochenplan', $announcement->title);
        $this->assertSame('weekplan', $announcement->related_module);
        $this->assertFalse($announcement->is_published);
        $this->assertNull($announcement->published_at);
        $this->assertSame($admin->id, $announcement->created_by);
    }

    public function test_creating_requires_a_title_and_description(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', '')
            ->set('formDescription', '')
            ->call('save')
            ->assertHasErrors(['formTitle', 'formDescription']);

        $this->assertSame(0, FeatureAnnouncement::count());
    }

    public function test_a_new_announcement_defaults_to_the_info_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->assertSet('formType', 'info')
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->call('save');

        $this->assertSame('info', FeatureAnnouncement::sole()->type);
    }

    public function test_an_admin_can_pick_a_non_default_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Wartung am Sonntag')
            ->set('formDescription', 'Von 2 bis 4 Uhr ist die App nicht erreichbar.')
            ->set('formType', 'maintenance')
            ->call('save');

        $this->assertSame('maintenance', FeatureAnnouncement::sole()->type);
    }

    public function test_type_must_be_a_real_catalog_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->set('formType', 'not-a-real-type')
            ->call('save')
            ->assertHasErrors(['formType']);
    }

    public function test_editing_an_announcement_loads_its_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Titel', 'description' => 'Beschreibung', 'type' => 'warning', 'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->call('startEdit', $announcement->id)
            ->assertSet('formType', 'warning');
    }

    public function test_related_module_must_be_a_real_catalog_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->set('formRelatedModule', 'not-a-real-module')
            ->call('save')
            ->assertHasErrors(['formRelatedModule']);
    }

    public function test_publishing_stamps_published_at_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Titel', 'description' => 'Beschreibung', 'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)->call('togglePublish', $announcement->id);

        $fresh = $announcement->fresh();
        $this->assertTrue($fresh->is_published);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_unpublishing_and_republishing_never_moves_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Titel', 'description' => 'Beschreibung', 'created_by' => $admin->id,
            'is_published' => true, 'published_at' => now()->subDays(3),
        ]);
        $originalPublishedAt = $announcement->published_at;

        $component = Livewire::actingAs($admin)->test(AnnouncementEditor::class);
        $component->call('togglePublish', $announcement->id); // unpublish
        $this->assertFalse($announcement->fresh()->is_published);

        $component->call('togglePublish', $announcement->id); // republish

        $this->assertTrue($announcement->fresh()->published_at->equalTo($originalPublishedAt));
    }

    public function test_an_admin_can_edit_an_existing_announcement(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Alter Titel', 'description' => 'Alte Beschreibung', 'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->call('startEdit', $announcement->id)
            ->assertSet('formTitle', 'Alter Titel')
            ->set('formTitle', 'Neuer Titel')
            ->call('save');

        $this->assertSame('Neuer Titel', $announcement->fresh()->title);
    }

    public function test_an_admin_can_delete_an_announcement(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Titel', 'description' => 'Beschreibung', 'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)->call('deleteAnnouncement', $announcement->id);

        $this->assertSame(0, FeatureAnnouncement::count());
    }

    public function test_the_preview_reflects_the_current_unsaved_form_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $preview = Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Neu: Wochenplan')
            ->set('formDescription', 'Plane deine wiederkehrende Woche an einem Ort.')
            ->set('formType', 'release')
            ->set('formRelatedModule', 'weekplan')
            ->instance()
            ->previewAnnouncement();

        $this->assertSame('Neu: Wochenplan', $preview->title);
        $this->assertSame('release', $preview->type);
        $this->assertSame('weekplan', $preview->related_module);
        $this->assertFalse($preview->exists);
        $this->assertSame(0, FeatureAnnouncement::count());
    }

    public function test_the_preview_never_persists_anything(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->assertOk();

        $this->assertSame(0, FeatureAnnouncement::count());
    }
}
