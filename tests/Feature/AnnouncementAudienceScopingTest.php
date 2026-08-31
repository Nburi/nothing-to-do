<?php

namespace Tests\Feature;

use App\Livewire\Admin\AnnouncementEditor;
use App\Livewire\FeatureAnnouncementToast;
use App\Models\FeatureAnnouncement;
use App\Models\ModuleVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FeatureAnnouncement's "only for module users" audience scoping — restricts
 * a module-linked announcement to people who have actually visited that
 * module's page (App\Models\ModuleVisit / App\Http\Middleware\RecordModuleVisit),
 * rather than a settings/visibility check. See CLAUDE.md, Feature-Ankündigungen.
 */
class AnnouncementAudienceScopingTest extends TestCase
{
    use RefreshDatabase;

    private function visit(User $user, string $moduleKey): void
    {
        ModuleVisit::query()->create(['user_id' => $user->id, 'module_key' => $moduleKey]);
    }

    private function published(array $overrides = []): FeatureAnnouncement
    {
        return FeatureAnnouncement::create(array_merge([
            'title' => 'Neu: Wochenplan',
            'description' => 'Plane deine wiederkehrende Woche an einem Ort.',
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    // ── FeatureAnnouncement statics ──────────────────────────────────

    public function test_planer_is_both_scopable_and_linkable(): void
    {
        $this->assertTrue(FeatureAnnouncement::isScopableModule('planner'));
        $this->assertArrayHasKey('planner', FeatureAnnouncement::linkableModules());
    }

    public function test_app_and_settings_are_linkable_but_not_scopable(): void
    {
        $this->assertArrayHasKey('app', FeatureAnnouncement::linkableModules());
        $this->assertArrayHasKey('settings', FeatureAnnouncement::linkableModules());
        $this->assertFalse(FeatureAnnouncement::isScopableModule('app'));
        $this->assertFalse(FeatureAnnouncement::isScopableModule('settings'));
    }

    public function test_module_key_for_route_name_resolves_a_scopable_route(): void
    {
        $this->assertSame('planner', FeatureAnnouncement::moduleKeyForRouteName('planner'));
        $this->assertSame('agenda', FeatureAnnouncement::moduleKeyForRouteName('agenda'));
    }

    public function test_module_key_for_route_name_returns_null_for_a_non_module_route(): void
    {
        $this->assertNull(FeatureAnnouncement::moduleKeyForRouteName('app'));
        $this->assertNull(FeatureAnnouncement::moduleKeyForRouteName('settings'));
        $this->assertNull(FeatureAnnouncement::moduleKeyForRouteName(null));
        $this->assertNull(FeatureAnnouncement::moduleKeyForRouteName('some-unknown-route'));
    }

    public function test_a_user_is_not_in_use_of_a_module_they_have_never_visited(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(FeatureAnnouncement::isModuleInUseBy($user, 'weekplan'));
    }

    public function test_a_user_is_in_use_of_a_module_once_a_visit_row_exists(): void
    {
        $user = User::factory()->create();
        $this->visit($user, 'weekplan');

        $this->assertTrue(FeatureAnnouncement::isModuleInUseBy($user, 'weekplan'));
    }

    public function test_module_reach_counts_reflect_real_visits(): void
    {
        $visitor = User::factory()->create();
        $stranger = User::factory()->create();
        $this->visit($visitor, 'agenda');

        $counts = FeatureAnnouncement::moduleReachCounts('agenda');

        $this->assertSame(2, $counts['total']);
        $this->assertSame(1, $counts['inUse']);
    }

    // ── AnnouncementEditor ────────────────────────────────────────────

    public function test_only_for_module_users_defaults_to_false(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->assertSet('formOnlyForModuleUsers', false);
    }

    public function test_an_admin_can_scope_an_announcement_to_module_visitors(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Neu: Wochenplan')
            ->set('formDescription', 'Plane deine wiederkehrende Woche an einem Ort.')
            ->set('formLinkType', 'module')
            ->set('formRelatedModule', 'weekplan')
            ->set('formOnlyForModuleUsers', true)
            ->call('save');

        $announcement = FeatureAnnouncement::sole();
        $this->assertSame('weekplan', $announcement->related_module);
        $this->assertTrue($announcement->only_for_module_users);
    }

    public function test_only_for_module_users_is_forced_false_when_link_type_is_not_module(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->set('formLinkType', 'module')
            ->set('formRelatedModule', 'weekplan')
            ->set('formOnlyForModuleUsers', true)
            ->set('formLinkType', 'external')
            ->set('formExternalUrl', 'https://example.test')
            ->call('save');

        $this->assertFalse(FeatureAnnouncement::sole()->only_for_module_users);
    }

    public function test_only_for_module_users_is_forced_false_for_a_non_scopable_module(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formTitle', 'Titel')
            ->set('formDescription', 'Beschreibung')
            ->set('formLinkType', 'module')
            ->set('formRelatedModule', 'settings')
            ->set('formOnlyForModuleUsers', true)
            ->call('save');

        $announcement = FeatureAnnouncement::sole();
        $this->assertSame('settings', $announcement->related_module);
        $this->assertFalse($announcement->only_for_module_users);
    }

    public function test_editing_an_announcement_loads_its_scoping_flag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $announcement = FeatureAnnouncement::create([
            'title' => 'Titel', 'description' => 'Beschreibung', 'created_by' => $admin->id,
            'related_module' => 'planner', 'only_for_module_users' => true,
        ]);

        Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->call('startEdit', $announcement->id)
            ->assertSet('formOnlyForModuleUsers', true);
    }

    public function test_module_usage_estimate_is_null_without_a_scopable_module_selected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $component = Livewire::actingAs($admin)->test(AnnouncementEditor::class);
        $this->assertNull($component->instance()->moduleUsageEstimate());

        $component->set('formLinkType', 'module')->set('formRelatedModule', 'app');
        $this->assertNull($component->instance()->moduleUsageEstimate());
    }

    public function test_module_usage_estimate_reflects_real_visit_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $visitor = User::factory()->create();
        $this->visit($visitor, 'agenda');

        $estimate = Livewire::actingAs($admin)->test(AnnouncementEditor::class)
            ->set('formLinkType', 'module')
            ->set('formRelatedModule', 'agenda')
            ->instance()
            ->moduleUsageEstimate();

        $this->assertSame(2, $estimate['total']); // admin + visitor
        $this->assertSame(1, $estimate['inUse']);
    }

    // ── Toast queue filtering ─────────────────────────────────────────

    public function test_an_unscoped_announcement_reaches_everyone_regardless_of_visits(): void
    {
        // The exact case this feature must not break: announcing a brand-new
        // opt-in page (e.g. "Planer is here now!") has to reach people who
        // have never opened it — that's the point of announcing it.
        $user = User::factory()->create();
        $announcement = $this->published(['related_module' => 'planner', 'only_for_module_users' => false]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_a_scoped_announcement_does_not_reach_a_user_who_has_never_visited_that_module(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published(['related_module' => 'weekplan', 'only_for_module_users' => true]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertDontSee($announcement->title);
    }

    public function test_a_scoped_announcement_reaches_a_user_who_has_visited_that_module(): void
    {
        $user = User::factory()->create();
        $this->visit($user, 'weekplan');
        $announcement = $this->published(['related_module' => 'weekplan', 'only_for_module_users' => true]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_a_scoped_announcement_only_reaches_the_visitor_not_a_stranger(): void
    {
        $visitor = User::factory()->create();
        $stranger = User::factory()->create();
        $this->visit($visitor, 'agenda');
        $announcement = $this->published(['related_module' => 'agenda', 'only_for_module_users' => true]);

        Livewire::actingAs($visitor)->test(FeatureAnnouncementToast::class)->assertSee($announcement->title);
        Livewire::actingAs($stranger)->test(FeatureAnnouncementToast::class)->assertDontSee($announcement->title);
    }

    public function test_a_scoped_announcement_is_ignored_if_it_somehow_has_no_related_module(): void
    {
        // Defense in depth: only_for_module_users is only ever saved true
        // alongside a real related_module (see AnnouncementEditor::save()),
        // but scopeUnseenBy() must fail open, not silently hide the row from
        // everyone, if that invariant is ever violated directly at the model.
        $user = User::factory()->create();
        $announcement = $this->published(['related_module' => null, 'only_for_module_users' => true]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_a_scoped_announcement_about_planer_reaches_a_visitor_even_though_planer_is_not_an_app_modules_catalog_entry(): void
    {
        $user = User::factory()->create();
        $this->visit($user, 'planner');
        $announcement = $this->published(['related_module' => 'planner', 'only_for_module_users' => true]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }
}
