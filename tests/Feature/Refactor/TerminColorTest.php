<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Week;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Planning\ChangeScope;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Termin colours (step 4.1, Niels 2026-11-24): a block without a category can wear one of the palette keys
 * the categories use. null = "Standard" = the current dark block, so existing data looks unchanged.
 * "Today" is Tuesday 17 November 2026, Zurich.
 */
class TerminColorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WeekPlanEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->editor = new WeekPlanEditor;
        $this->actingAs($this->user);
    }

    private function tint(string $date, string $title): ?string
    {
        foreach ((new WeekPlanReader)->blocks($this->user, $date, $date)[$date] as $block) {
            if ($block->title === $title) {
                return $block->tint;
            }
        }
        $this->fail("No block {$title} on {$date}");
    }

    public function test_the_migration_is_additive_and_leaves_existing_rows_at_standard(): void
    {
        $this->assertTrue(Schema::hasColumn('schedule_events', 'termin_color'));
        $this->assertTrue(Schema::hasColumn('event_templates', 'termin_color'));

        // A row written the way the old code did (no termin_color, colour 'forest') reads back as null = Standard.
        DB::table('schedule_events')->insert(['user_id' => $this->user->id, 'title' => 'Alt', 'color' => 'forest', 'date' => '2026-11-18', 'start_time' => '10:00', 'end_time' => '11:00', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNull(ScheduleEvent::where('title', 'Alt')->value('termin_color'));
        $this->assertNull($this->tint('2026-11-18', 'Alt'));
    }

    public function test_a_termin_keeps_the_chosen_palette_key(): void
    {
        $event = $this->editor->addOnce($this->user, '2026-11-18', '10:00', '11:00', null, 'Zahnarzt', 'forest');

        $this->assertSame('forest', $event->fresh()->termin_color);
        $this->assertSame('forest', $event->fresh()->color, 'the old screens, API and MCP read `color`');
        $this->assertSame('forest', $this->tint('2026-11-18', 'Zahnarzt'));
    }

    public function test_standard_stores_nothing_and_keeps_the_old_default(): void
    {
        $event = $this->editor->addOnce($this->user, '2026-11-18', '10:00', '11:00', null, 'Zahnarzt');

        $this->assertNull($event->fresh()->termin_color);
        $this->assertSame('contour', $event->fresh()->color);
        $this->assertNull($this->tint('2026-11-18', 'Zahnarzt'));
    }

    public function test_only_palette_keys_are_accepted(): void
    {
        foreach (['#ff0000', 'pink', 'deep'] as $bad) {
            try {
                $this->editor->addOnce($this->user, '2026-11-18', '10:00', '11:00', null, 'Zahnarzt', $bad);
                $this->fail("{$bad} was accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('color', $e->errors());
            }
        }
        $this->assertSame(0, ScheduleEvent::count());
    }

    public function test_a_category_block_ignores_a_colour_and_a_termin_turned_into_one_loses_it(): void
    {
        $cat = EventCategory::factory()->for($this->user)->create(['name' => 'Training', 'color' => 'signal']);
        $block = $this->editor->addOnce($this->user, '2026-11-18', '18:00', '19:00', $cat->id, null, 'forest');
        $this->assertNull($block->fresh()->termin_color);
        $this->assertSame('signal', $block->fresh()->color);

        $termin = $this->editor->addOnce($this->user, '2026-11-19', '10:00', '11:00', null, 'Zahnarzt', 'forest');
        $this->editor->updateOnce($this->user, $termin->id, '2026-11-19', '10:00', '11:00', $cat->id);
        $this->assertNull($termin->fresh()->termin_color);
    }

    public function test_a_repeating_termin_and_its_occurrences_wear_the_colour(): void
    {
        $template = $this->editor->addRepeating($this->user, [2, 4], '09:00', '10:00', null, 'Physio', 'overprint');

        $this->assertSame('overprint', $template->fresh()->termin_color);
        $this->assertSame('overprint', $this->tint('2026-11-17', 'Physio'));
        $this->assertSame('overprint', $this->tint('2026-11-19', 'Physio'));

        // Changed for one week only: still the series' colour.
        $this->editor->changeOccurrence($this->user, $template->id, '2026-11-19', '09:30', '10:30', null, ChangeScope::ThisWeekOnly);
        $this->assertSame('overprint', $this->tint('2026-11-19', 'Physio'));
    }

    public function test_changing_the_colour_in_the_wochenplan_and_back_to_standard(): void
    {
        $template = $this->editor->addRepeating($this->user, [2], '09:00', '10:00', null, 'Physio', 'overprint');

        $this->editor->updateTemplate($this->user, $template->id, [2], '09:00', '10:00', null, 'Physio', 'signal');
        $this->assertSame('signal', $this->tint('2026-11-17', 'Physio'));

        $this->editor->updateTemplate($this->user, $template->id, [2], '09:00', '10:00', null, 'Physio');
        $this->assertNull($this->tint('2026-11-17', 'Physio'));
    }

    public function test_standard_on_an_old_termin_keeps_the_colour_the_old_screens_stored(): void
    {
        $id = DB::table('schedule_events')->insertGetId(['user_id' => $this->user->id, 'title' => 'Alt', 'color' => 'forest', 'date' => '2026-11-18', 'start_time' => '10:00', 'end_time' => '11:00', 'created_at' => now(), 'updated_at' => now()]);

        $this->editor->updateOnce($this->user, $id, '2026-11-18', '10:30', '11:30', null, 'Alt');

        $row = ScheduleEvent::find($id);
        $this->assertSame('forest', $row->color, 'a time change must not rewrite the old colour');
        $this->assertNull($row->termin_color);
    }

    public function test_a_colour_written_by_the_api_or_the_old_forms_reaches_the_grid(): void
    {
        // Same path as ScheduleEventController / MCP / the old forms: a model write with an explicit `color`.
        $event = $this->user->scheduleEvents()->create(['title' => 'Api', 'color' => 'signal', 'date' => '2026-11-18', 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->assertSame('signal', $event->fresh()->termin_color);

        $event->update(['color' => 'ink']);
        $this->assertSame('ink', $event->fresh()->termin_color);

        // An unrelated update does not touch it, and a writer that sets termin_color itself wins.
        $event->update(['title' => 'Api 2']);
        $this->assertSame('ink', $event->fresh()->termin_color);
        $event->update(['color' => 'forest', 'termin_color' => null]);
        $this->assertNull($event->fresh()->termin_color);

        // A category block and an occurrence row follow their category / template, never the copy.
        $cat = EventCategory::factory()->for($this->user)->create(['color' => 'forest']);
        $withCat = $this->user->scheduleEvents()->create(['category_id' => $cat->id, 'title' => 'C', 'color' => 'forest', 'date' => '2026-11-18', 'start_time' => '12:00', 'end_time' => '13:00']);
        $this->assertNull($withCat->fresh()->termin_color);
        $template = $this->editor->addRepeating($this->user, [3], '14:00', '15:00', null, 'Serie', 'signal');
        $row = $this->user->scheduleEvents()->create(['template_id' => $template->id, 'title' => 'Serie', 'color' => 'contour', 'date' => '2026-11-18', 'start_time' => '14:00', 'end_time' => '15:00']);
        $this->assertNull($row->fresh()->termin_color);
    }

    public function test_the_form_offers_standard_and_the_five_palette_colours_and_saves_the_choice(): void
    {
        $page = Livewire::test(Week::class)
            ->call('openNew', [3], '10:00', '11:00')
            ->set('form.kind', 'termin')
            ->set('form.title', 'Zahnarzt');

        $html = $page->html();
        $this->assertSame(6, substr_count($html, 'data-color='));
        foreach (['standard', 'contour', 'overprint', 'forest', 'signal', 'ink'] as $key) {
            $this->assertStringContainsString('data-color="'.$key.'"', $html);
        }

        $page->set('form.color', 'forest')->call('save', 'this')->assertHasNoErrors();

        $row = ScheduleEvent::where('title', 'Zahnarzt')->firstOrFail();
        $this->assertSame('forest', $row->termin_color);
        $this->assertStringContainsString('var(--color-forest)', Livewire::test(Week::class)->html());
    }

    public function test_an_untouched_termin_keeps_the_dark_standard_look(): void
    {
        $this->editor->addOnce($this->user, '2026-11-18', '10:00', '11:00', null, 'Zahnarzt');

        preg_match('/<button[^>]*data-block[^>]*aria-label="10:00–11:00 Zahnarzt[^>]*>/', Livewire::test(Week::class)->html(), $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('bg-deep text-on-deep', $m[0]);
        $this->assertStringNotContainsString('color-mix', $m[0]);
    }

    public function test_a_coloured_termin_wears_its_colour_in_the_grid(): void
    {
        $this->editor->addOnce($this->user, '2026-11-18', '10:00', '11:00', null, 'Zahnarzt', 'overprint');

        preg_match('/<button[^>]*data-block[^>]*aria-label="10:00–11:00 Zahnarzt[^>]*>/', Livewire::test(Week::class)->html(), $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('bg-deep', $m[0]);
        $this->assertStringContainsString('var(--color-overprint) 20%', $m[0]);
    }

    public function test_the_colour_of_an_occurrence_is_read_only_and_comes_from_the_wochenplan(): void
    {
        $template = $this->editor->addRepeating($this->user, [2], '09:00', '10:00', null, 'Physio', 'signal');

        $html = Livewire::test(Week::class)->call('openBlock', 'occ', $template->id, '2026-11-17')->html();

        $this->assertStringContainsString('Die Farbe kommt aus dem Wochenplan', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-color="signal"[^>]*disabled/s', $html);
    }

    public function test_the_wochenplan_edits_the_colour_and_undo_brings_the_old_one_back(): void
    {
        $template = $this->editor->addRepeating($this->user, [2], '09:00', '10:00', null, 'Physio', 'signal');

        Livewire::test(Week::class, ['view' => 'plan'])
            ->call('openBlock', 'tpl', $template->id, '2')
            ->assertSet('form.color', 'signal')
            ->set('form.color', 'ink')
            ->call('save')
            ->assertSee('Im Wochenplan, ab morgen jede Woche')
            ->call('undoLast');

        $this->assertSame('signal', $template->fresh()->termin_color);
    }

    public function test_moving_an_occurrence_every_week_to_another_day_keeps_the_series_colour(): void
    {
        $template = $this->editor->addRepeating($this->user, [2], '09:00', '10:00', null, 'Physio', 'signal');

        Livewire::test(Week::class)
            ->call('openBlock', 'occ', $template->id, '2026-11-17')
            ->set('form.newDate', '2026-11-18')
            ->call('save', 'every');

        $this->assertSame('signal', $template->fresh()->termin_color);
        $this->assertSame([3], $template->fresh()->recurrenceDays());
    }

    public function test_foreign_rows_are_untouched(): void
    {
        $other = User::factory()->create();
        $theirs = $this->editor->addOnce($other, '2026-11-18', '10:00', '11:00', null, 'Fremd', 'signal');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->editor->updateOnce($this->user, $theirs->id, '2026-11-18', '10:00', '11:00', null, 'Fremd', 'forest');
    }
}
