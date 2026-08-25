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
        $user = User::factory()->create();
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

    public function test_nothing_renders_when_the_queue_is_empty(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)->test(FeatureAnnouncementToast::class)->html();

        $this->assertStringNotContainsString('role="status"', $html);
    }
}
