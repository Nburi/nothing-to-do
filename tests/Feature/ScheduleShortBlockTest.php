<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\DayWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Short blocks: the clipped body, the height-driven density tiers, and the
 * Signature Moment that lifts a block too short to carry its own content.
 *
 * Most of this is CSS and Alpine, which a server-side test cannot exercise.
 * What it can pin down is the contract between the three layers — the classes
 * the container queries key on, the hooks the lift needs, and the one number
 * that has to be the same in PHP, CSS and JS.
 */
class ScheduleShortBlockTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_a_block_is_a_wrapper_around_a_clipped_body(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('13:00', '13:20')->create(['title' => 'Arbeiten']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        // The wrapper positions and carries the gestures and is never clipped;
        // the body is what clips, and what the container queries measure.
        $response->assertSee('tl-block group absolute', false);
        $response->assertSee('tl-body rounded-[7px] border', false);
        // Geometry moved into the Alpine getter, so the lift can add a min-height
        // to it without a second style binding fighting the first.
        $response->assertSee('x-bind:style="blockStyle"', false);
    }

    public function test_every_block_carries_the_hooks_the_lift_needs(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('13:00', '13:20')->create(['title' => 'Arbeiten']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        // The document-level "settle on a pointer outside any block" listener
        // finds blocks by this attribute.
        $response->assertSee('data-schedule-block', false);
        // Only one block lifted at a time.
        $response->assertSee('schedule-block-settle.window', false);
        // Hover lifts on a mouse only, read from the event's own pointerType
        // rather than a device-level media query.
        $response->assertSee("pointerType === 'mouse'", false);
    }

    public function test_the_pencil_is_gated_by_the_blocks_height_not_by_its_duration(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('13:00', '13:20')->create(['title' => 'Arbeiten']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        // Present in the DOM for every block; whether it is displayed is decided
        // by the @container query, and whether it is visible by hover or lift.
        $response->assertSee('tl-opt tl-opt-pencil', false);
        $response->assertSee('lifted && \'opacity-100\'', false);
    }

    public function test_the_time_line_is_an_optional_tier(): void
    {
        $user = $this->actingUser();
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('13:00', '13:20')->create(['title' => 'Arbeiten']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('tl-time tl-opt tl-opt-time', false);
    }

    public function test_both_breakpoints_say_how_to_reach_a_short_blocks_editor(): void
    {
        $this->actingUser();

        $response = $this->get('/app/schedule');

        $response->assertOk();
        // Desktop footer.
        $response->assertSee('kurze Blöcke richten sich beim Draufzeigen auf', false);
        // Mobile, where the pencil never existed at all before this.
        $response->assertSee('Kurze Blöcke antippen, um sie aufzurichten', false);
    }

    public function test_the_wochenplan_got_the_same_treatment(): void
    {
        $user = $this->actingUser();
        EventTemplate::factory()->for($user)->create([
            'name' => 'Schule',
            'duration' => 20,
            'default_start' => '08:00',
            'is_recurring' => true,
            'recurrence' => '1,2,3,4,5',
        ]);

        $response = $this->get('/app/weekplan');

        $response->assertOk();
        $response->assertSee('tl-body rounded-[7px] border', false);
        $response->assertSee('data-schedule-block', false);
        $response->assertSee('x-bind:style="blockStyle"', false);
        $response->assertSee('Kurze Blöcke antippen, um sie aufzurichten', false);
    }

    public function test_a_pomodoro_block_still_shows_its_clock_and_its_title(): void
    {
        $user = $this->actingUser();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Arbeiten', 'pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('13:00', '13:20')->create(['category_id' => $category->id, 'title' => 'Arbeiten']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('Arbeiten', false)
            // The clock icon lives in the title row, which is the one row a block
            // never drops however short it is.
            ->assertSee('tl-title flex items-center', false);
    }

    /**
     * The lift height exists three times — as a PHP constant, as the
     * `@container (min-height: …)` thresholds, and as LIFT_MIN_PX in the Alpine
     * component. None of the three can read the others, so this is the guard
     * that keeps them from drifting apart silently (CLAUDE.md §10 documents the
     * same hazard for classes that only exist in JS).
     */
    public function test_the_lift_clears_the_tier_threshold_it_exists_to_reach(): void
    {
        // Found in a browser, not by a test: a container query sizes against the
        // container's content box, the lift sets a min-height on a border box,
        // and the body's 1px border top and bottom left a lifted block two
        // pixels short of the tier it was lifted for.
        $this->assertGreaterThan(DayWindow::TIER_FULL_PX, DayWindow::LIFT_MIN_PX);
        $this->assertSame(46, DayWindow::TIER_FULL_PX);
        $this->assertSame(50, DayWindow::LIFT_MIN_PX);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('@container (min-height: '.DayWindow::TIER_FULL_PX.'px)', $css);
        $this->assertStringContainsString('.tl-block-lifted', $css);

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('const LIFT_MIN_PX = '.DayWindow::LIFT_MIN_PX.';', $js);
        $this->assertStringContainsString('min-height:${LIFT_MIN_PX}px', $js);
    }

    /**
     * The tiers are plain CSS, deliberately not inside Tailwind's
     * `@layer components` — that layer is tree-shaken by class name, and a
     * nested `@container` at-rule is exactly the shape that is risky to hand to
     * that scanner.
     */
    /**
     * The pencil draws at 24px, and touch is the one path where it is the only
     * way in — group-hover never fires there. A transparent ::after widens the
     * tappable area to ~37px without changing the look; the body's own
     * overflow:hidden clips the rest, which is why it is not the full 44.
     */
    public function test_the_pencil_has_a_wider_touch_target_than_it_draws(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.tl-opt-pencil::after', $css);
        $this->assertMatchesRegularExpression(
            '/\.tl-opt-pencil::after\s*\{[^}]*inset:\s*-\d+px/s',
            $css,
            'the pencil no longer widens its own hit area',
        );
    }

    public function test_the_density_tiers_are_guarded_for_browsers_without_container_queries(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@supports (container-type: size)', $css);
        // Everything that hides a tier sits inside the @supports block, so a
        // browser without support shows all of them rather than none.
        $supports = substr($css, strpos($css, '@supports (container-type: size)'));
        $this->assertStringContainsString('.tl-opt { display: none; }', $supports);
    }
}
