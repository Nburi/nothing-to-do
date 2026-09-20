<?php

namespace Tests\Feature;

use App\Livewire\HeaderBadgeRow;
use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The header badge row as a live Livewire component (see HeaderBadgeRow):
 * it refreshes independently of the page component that handled an action,
 * and only flags a badge as changed/appeared on a refresh — never on the
 * first render, so arriving on a page plays no animation.
 */
class HeaderBadgeRowTest extends TestCase
{
    use RefreshDatabase;

    private function goalUser(int $goal = 5): User
    {
        return User::factory()->create([
            'timezone_offset' => 0,
            'daily_task_goal' => $goal,
            'header_badges' => [
                ['key' => 'goal', 'enabled' => true],
                ['key' => 'agenda', 'enabled' => true],
            ],
        ]);
    }

    private function completeTask(User $user): Task
    {
        return Task::factory()->for($user)->completed()->create(['completed_at' => now()]);
    }

    public function test_the_layout_mounts_the_badge_row_as_its_own_component(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app')->assertOk()->assertSeeLivewire(HeaderBadgeRow::class);
    }

    public function test_the_first_render_shows_the_badges_without_flagging_anything_as_changed(): void
    {
        $user = $this->goalUser();
        $this->completeTask($user);

        Livewire::actingAs($user)->test(HeaderBadgeRow::class)
            ->assertSee('1/5')
            ->assertDontSeeHtml('badge-value-in')
            ->assertDontSeeHtml('badge-wash')
            ->assertDontSeeHtml('badge-grow-in');
    }

    public function test_a_refresh_after_a_completion_shows_the_new_count_and_animates_it(): void
    {
        $user = $this->goalUser();
        $this->completeTask($user);

        $component = Livewire::actingAs($user)->test(HeaderBadgeRow::class)->assertSee('1/5');

        $this->completeTask($user);

        $component->call('$refresh')
            ->assertSee('2/5')
            ->assertSeeHtml('badge-value-in')
            ->assertSeeHtml('badge-wash');
    }

    public function test_an_unchanged_refresh_flags_nothing(): void
    {
        $user = $this->goalUser();
        $this->completeTask($user);

        Livewire::actingAs($user)->test(HeaderBadgeRow::class)
            ->call('$refresh')
            ->assertSee('1/5')
            ->assertDontSeeHtml('badge-value-in')
            ->assertDontSeeHtml('badge-wash');
    }

    public function test_a_badge_that_gains_something_to_show_appears_live_and_grows_in(): void
    {
        $user = $this->goalUser();

        // Nothing completed yet: the goal badge is hidden, not shown as 0/5.
        $component = Livewire::actingAs($user)->test(HeaderBadgeRow::class)
            ->assertDontSee('0/5')
            ->assertDontSeeHtml('Fortschritt ansehen');

        $this->completeTask($user);

        $component->call('$refresh')
            ->assertSee('1/5')
            ->assertSeeHtml('badge-grow-in');
    }

    public function test_a_badge_that_runs_out_of_things_to_show_leaves_as_an_inert_ghost_for_one_render_then_is_gone(): void
    {
        $user = $this->goalUser();
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['date' => now()->addDay()->toDateString()]);

        $component = Livewire::actingAs($user)->test(HeaderBadgeRow::class)
            ->assertSeeHtml('Agenda ansehen');

        $entry->toggleDoneFor($user);

        // One last render as a collapsing, non-interactive ghost: no link, no tooltip.
        $component->call('$refresh')
            ->assertSeeHtml('badge-collapse-out')
            ->assertDontSeeHtml('Agenda ansehen')
            ->assertDontSeeHtml('href="'.route('agenda').'"');

        // The next refresh drops it for good.
        $component->call('$refresh')->assertDontSeeHtml('badge-collapse-out');
    }

    public function test_the_replaced_value_is_rendered_once_more_as_a_rolling_out_ghost(): void
    {
        $user = $this->goalUser();
        $this->completeTask($user);

        $component = Livewire::actingAs($user)->test(HeaderBadgeRow::class);

        $this->completeTask($user);

        $component->call('$refresh')
            ->assertSeeHtml('badge-value-out')
            ->call('$refresh')
            ->assertDontSeeHtml('badge-value-out');
    }

    public function test_a_first_render_never_produces_ghosts(): void
    {
        $user = $this->goalUser();
        $this->completeTask($user);

        Livewire::actingAs($user)->test(HeaderBadgeRow::class)
            ->assertDontSeeHtml('badge-collapse-out')
            ->assertDontSeeHtml('badge-value-out');
    }

    public function test_the_streak_badge_never_takes_the_wash_so_its_own_flame_owns_that_moment(): void
    {
        $badge = [
            'key' => 'streak', 'label' => 'Serie', 'route' => 'progress', 'tone' => 'ink', 'icon' => 'streak',
            'text' => '4', 'title' => '4 Tage Serie', 'href' => '/app/progress', 'tier' => 2,
            'changed' => true, 'appeared' => false,
        ];

        $html = view('partials.header-badge', ['badge' => $badge])->render();

        $this->assertStringContainsString('badge-value-in', $html);
        $this->assertStringNotContainsString('badge-wash', $html);
        $this->assertStringContainsString('data-badge="streak"', $html);
    }

    public function test_the_seen_map_cannot_be_written_from_the_client(): void
    {
        $user = $this->goalUser();

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(HeaderBadgeRow::class)->set('seen', []);
    }
}
