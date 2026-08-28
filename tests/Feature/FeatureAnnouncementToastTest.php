<?php

namespace Tests\Feature;

use App\Livewire\FeatureAnnouncementToast;
use App\Models\FeatureAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureAnnouncementToastTest extends TestCase
{
    use RefreshDatabase;

    private function published(array $overrides = []): FeatureAnnouncement
    {
        return FeatureAnnouncement::create(array_merge([
            'title' => 'Neu: Wochenplan',
            'description' => 'Plane deine wiederkehrende Woche an einem Ort.',
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_a_draft_announcement_is_never_shown(): void
    {
        $user = User::factory()->create();
        FeatureAnnouncement::create([
            'title' => 'Entwurf', 'description' => 'Noch nicht fertig.', 'is_published' => false,
        ]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertDontSee('Entwurf');
    }

    public function test_a_published_announcement_shows_for_a_user_who_has_not_seen_it(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published();

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title)
            ->assertSee($announcement->description);
    }

    public function test_dismissing_hides_it_and_it_never_reappears(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published();

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->call('dismiss', $announcement->id)
            ->assertDontSee($announcement->title);

        $this->assertTrue($announcement->isDismissedBy($user));

        // A fresh mount (e.g. the next page load) must not resurface it.
        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertDontSee($announcement->title);
    }

    public function test_dismissing_is_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $announcement = $this->published();

        Livewire::actingAs($userA)->test(FeatureAnnouncementToast::class)->call('dismiss', $announcement->id);

        Livewire::actingAs($userB)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_multiple_unseen_announcements_show_one_at_a_time_oldest_first(): void
    {
        // Backdated so this reads as an existing user with a two-item
        // backlog, not a brand-new account — a new user wouldn't see either
        // of these under the new-user filtering added alongside message
        // types (see scopeUnseenBy's own docblock).
        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $older = $this->published(['title' => 'Älter', 'published_at' => now()->subDays(2)]);
        $newer = $this->published(['title' => 'Neuer', 'published_at' => now()->subDay()]);

        $component = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class);
        $component->assertSee('Älter')->assertDontSee('Neuer');
        $component->assertSet('remainingAfterCurrent', 1);

        $component->call('dismiss', $older->id);
        $component->assertSee('Neuer')->assertDontSee('Älter');
        $component->assertSet('remainingAfterCurrent', 0);
    }

    public function test_dismissing_twice_is_a_no_op_not_an_error(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published();

        $component = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class);
        $component->call('dismiss', $announcement->id);
        $component->call('dismiss', $announcement->id);

        $this->assertSame(1, $announcement->dismissedBy()->count());
    }

    public function test_an_announcement_with_no_related_module_shows_no_link(): void
    {
        $user = User::factory()->create();
        $this->published(['related_module' => null]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertDontSee('ansehen');
    }

    public function test_an_announcement_with_a_related_module_links_to_it_and_dismisses_on_click(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published(['related_module' => 'agenda']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Agenda ansehen')
            ->call('dismiss', $announcement->id);

        $this->assertTrue($announcement->isDismissedBy($user));
    }

    public function test_a_new_user_does_not_see_an_announcement_published_before_they_registered(): void
    {
        $announcement = $this->published(['published_at' => now()->subDay()]);

        // Registers "now", strictly after the announcement went out.
        $newUser = User::factory()->create();

        Livewire::actingAs($newUser)->test(FeatureAnnouncementToast::class)
            ->assertDontSee($announcement->title);
    }

    public function test_an_existing_user_still_sees_an_announcement_published_after_they_registered(): void
    {
        $existingUser = User::factory()->create();
        $existingUser->forceFill(['created_at' => now()->subDays(30)])->save();

        $announcement = $this->published();

        Livewire::actingAs($existingUser)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_a_new_user_still_sees_an_announcement_published_right_at_registration(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published(['published_at' => $user->created_at]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee($announcement->title);
    }

    public function test_the_toast_shows_the_badge_label_for_the_announcements_type(): void
    {
        $user = User::factory()->create();
        $this->published(['type' => 'warning']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Warnung');
    }

    public function test_an_announcement_with_an_unknown_type_falls_back_to_info(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published();
        $announcement->forceFill(['type' => 'some-removed-type'])->save();

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Neu')
            ->assertSee($announcement->title);
    }

    public function test_an_announcement_with_an_external_link_opens_in_a_new_tab_and_dismisses_on_click(): void
    {
        $user = User::factory()->create();
        $announcement = $this->published([
            'external_url' => 'https://example.test/blog',
            'external_link_label' => 'Blogpost lesen',
        ]);

        $component = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Blogpost lesen');

        $this->assertStringNotContainsString('wire:navigate', $component->html());
        $this->assertStringContainsString('target="_blank"', $component->html());

        $component->call('dismiss', $announcement->id);
        $this->assertTrue($announcement->isDismissedBy($user));
    }

    public function test_a_module_link_with_a_highlight_selector_carries_it_as_a_query_param(): void
    {
        $user = User::factory()->create();
        $this->published(['related_module' => 'agenda', 'highlight_selector' => '#agenda-spaces']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSeeHtml('highlight=%23agenda-spaces');
    }

    public function test_nothing_renders_when_the_queue_is_empty(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)->html();

        $this->assertStringNotContainsString('role="status"', $html);
    }

    public function test_a_single_announcement_shows_no_progress_counter_or_skip_all(): void
    {
        $user = User::factory()->create();
        $this->published();

        $html = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)->html();

        $this->assertStringNotContainsString('1 von 1', $html);
        $this->assertStringNotContainsString('Alle überspringen', $html);
    }

    public function test_a_backlog_shows_a_position_counter_that_holds_steady_as_the_total(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $first = $this->published(['title' => 'Erste', 'published_at' => now()->subDays(2)]);
        $this->published(['title' => 'Zweite', 'published_at' => now()->subDay()]);
        $this->published(['title' => 'Dritte', 'published_at' => now()]);

        $component = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class);
        $component->assertSee('1 von 3')->assertSee('Alle überspringen');

        $component->call('dismiss', $first->id);
        // Total stays 3 even though only 2 remain — the numerator moves, the
        // denominator (initialQueueTotal, captured once at mount) does not.
        $component->assertSee('2 von 3');
    }

    public function test_skip_all_dismisses_every_queued_announcement_at_once(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $first = $this->published(['title' => 'Alpha-Ankündigung', 'published_at' => now()->subDays(2)]);
        $second = $this->published(['title' => 'Beta-Ankündigung', 'published_at' => now()->subDay()]);
        $third = $this->published(['title' => 'Gamma-Ankündigung', 'published_at' => now()]);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->call('skipAll')
            ->assertDontSee('Alpha-Ankündigung')
            ->assertDontSee('Beta-Ankündigung')
            ->assertDontSee('Gamma-Ankündigung');

        $this->assertTrue($first->isDismissedBy($user));
        $this->assertTrue($second->isDismissedBy($user));
        $this->assertTrue($third->isDismissedBy($user));
    }

    public function test_skip_all_on_an_empty_queue_is_a_no_op(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)->call('skipAll');

        $this->assertDatabaseCount('feature_announcement_dismissals', 0);
    }

    public function test_a_long_gap_return_shows_a_welcome_message_from_the_catalog(): void
    {
        $user = User::factory()->create();
        session(['welcome_back_message' => 'Schön, dass du wieder da bist.']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Schön, dass du wieder da bist.');
    }

    public function test_the_welcome_message_only_appears_once_even_across_several_announcements(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $first = $this->published(['title' => 'Erste', 'published_at' => now()->subDays(2)]);
        $this->published(['title' => 'Zweite', 'published_at' => now()->subDay()]);
        session(['welcome_back_message' => 'Gut, dich wiederzusehen.']);

        $component = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class);
        $component->assertSee('Gut, dich wiederzusehen.');

        $component->call('dismiss', $first->id);
        $component->assertDontSee('Gut, dich wiederzusehen.');
    }

    public function test_the_welcome_message_does_not_resurface_after_the_backlog_drains_via_dismiss(): void
    {
        // Regression: welcomeMessage lives on the component for its whole
        // lifetime, not just the render it was shown on. Draining the queue
        // to empty must not let it come back as a standalone card once
        // current() goes null — it was already shown, fused into card 1.
        $user = User::factory()->create();
        $only = $this->published(['title' => 'Einzige Ankündigung']);
        session(['welcome_back_message' => 'Willkommen zurück. Nichts ist verloren gegangen.']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Willkommen zurück. Nichts ist verloren gegangen.')
            ->call('dismiss', $only->id)
            ->assertDontSee('Willkommen zurück. Nichts ist verloren gegangen.')
            ->assertDontSee('role="status"');
    }

    public function test_the_welcome_message_does_not_resurface_after_skip_all(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $this->published(['title' => 'Erste', 'published_at' => now()->subDays(2)]);
        $this->published(['title' => 'Zweite', 'published_at' => now()->subDay()]);
        session(['welcome_back_message' => 'Zeit, wieder reinzukommen.']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Zeit, wieder reinzukommen.')
            ->call('skipAll')
            ->assertDontSee('Zeit, wieder reinzukommen.')
            ->assertDontSee('role="status"');
    }

    public function test_a_welcome_message_with_no_queued_announcements_renders_as_a_standalone_card(): void
    {
        $user = User::factory()->create();
        session(['welcome_back_message' => 'Da bist du ja wieder.']);

        $html = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)->html();

        $this->assertStringContainsString('Da bist du ja wieder.', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    public function test_the_session_flash_is_consumed_and_does_not_reappear_on_a_fresh_mount(): void
    {
        $user = User::factory()->create();
        session(['welcome_back_message' => 'Zurück im Sattel.']);

        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertSee('Zurück im Sattel.');

        // A fresh component instance (e.g. the next wire:navigate page load)
        // must not show it again — session()->pull() already consumed it.
        Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)
            ->assertDontSee('Zurück im Sattel.');
    }
}
