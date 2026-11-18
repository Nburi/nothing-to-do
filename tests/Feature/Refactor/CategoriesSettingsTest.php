<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Categories;
use App\Models\Area;
use App\Models\EventCategory;
use App\Models\User;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A22 (step 4.1; R10, REQUIREMENTS § 4): categories in the shell settings. Create, rename, colour,
 * the pomodoro switch, linked areas with "+ Bereich", delete with undo (soft delete: the blocks
 * keep name and colour and stop counting as work time).
 */
class CategoriesSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $this->actingAs($this->user);
    }

    public function test_the_settings_page_shows_the_categories(): void
    {
        EventCategory::factory()->for($this->user)->create(['name' => 'Deep work']);

        $this->get('/board/settings')->assertOk()->assertSee('data-card="categories"', false)->assertSee('Deep work');
    }

    public function test_a_category_is_created_with_colour_and_pomodoro_switch(): void
    {
        Livewire::test(Categories::class)
            ->set('newName', '  Schule ')->set('newColor', 'overprint')->set('newPomodoro', true)
            ->call('add')->assertHasNoErrors();

        $category = EventCategory::forUser($this->user)->sole();
        $this->assertSame(['Schule', 'overprint', true], [$category->name, $category->color, $category->pomodoro_enabled]);
    }

    public function test_an_empty_name_is_refused_inline_in_german(): void
    {
        Livewire::test(Categories::class)->set('newName', '   ')->call('add')
            ->assertHasErrors(['newName' => 'required'])->assertSee('Name muss ausgefüllt werden');

        $this->assertSame(0, EventCategory::count());
    }

    public function test_rename_colour_and_switch(): void
    {
        $category = EventCategory::factory()->for($this->user)->create(['name' => 'Deep', 'color' => 'forest', 'pomodoro_enabled' => false]);

        Livewire::test(Categories::class)
            ->call('rename', $category->id, 'Deep work')
            ->call('setColor', $category->id, 'signal')
            ->call('setColor', $category->id, 'not-a-colour')
            ->call('togglePomodoro', $category->id);

        $category->refresh();
        $this->assertSame(['Deep work', 'signal', true], [$category->name, $category->color, $category->pomodoro_enabled]);
    }

    public function test_an_empty_rename_shows_the_error_under_that_row(): void
    {
        $category = EventCategory::factory()->for($this->user)->create(['name' => 'Deep work']);

        Livewire::test(Categories::class)->call('rename', $category->id, ' ')->assertHasErrors(['rename.'.$category->id]);
        $this->assertSame('Deep work', $category->fresh()->name);
    }

    public function test_areas_are_linked_and_created_right_there(): void
    {
        $category = EventCategory::factory()->for($this->user)->pomodoro()->create();
        $school = Area::factory()->for($this->user)->create(['name' => 'Schule']);
        $foreign = Area::factory()->create();

        Livewire::test(Categories::class)
            ->call('toggleArea', $category->id, $school->id)
            ->call('toggleArea', $category->id, $foreign->id)
            ->call('openArea', $category->id)->set('areaName', 'Eigene Projekte')->call('addArea')
            ->call('openArea', $category->id)->set('areaName', 'schule')->call('addArea')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['Schule', 'Eigene Projekte'], $category->fresh()->areas->pluck('name')->all());
        $this->assertSame(2, Area::forUser($this->user)->count(), '"schule" reuses the existing area');
    }

    public function test_a_new_area_needs_a_name(): void
    {
        $category = EventCategory::factory()->for($this->user)->pomodoro()->create();

        Livewire::test(Categories::class)->call('openArea', $category->id)->set('areaName', '')->call('addArea')->assertHasErrors(['areaName']);
    }

    public function test_deleting_keeps_the_blocks_and_undo_brings_it_back(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work', 'color' => 'forest']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
        $sessions = fn () => count((new WeekPlanReader)->slots($this->user, '2026-11-10', '2026-11-10')['2026-11-10']);
        $this->assertGreaterThan(0, $sessions());

        $component = Livewire::test(Categories::class)->call('delete', $deep->id)->assertSee('Rückgängig');

        $this->assertSoftDeleted($deep);
        [$block] = (new WeekPlanReader)->blocks($this->user, '2026-11-10', '2026-11-10')['2026-11-10'];
        $this->assertSame('Deep work', $block->title, 'the block keeps its name');
        $this->assertSame(0, $sessions(), 'and is no longer work time');

        $component->call('undo');
        $this->assertNotSoftDeleted($deep);
        $this->assertGreaterThan(0, $sessions());
    }

    public function test_a_deleted_category_cannot_be_given_to_a_block(): void
    {
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create();
        $deep->delete();

        $this->expectException(ValidationException::class);
        (new WeekPlanEditor)->addOnce($this->user, '2026-11-10', '10:00', '11:00', $deep->id);
    }

    public function test_another_users_category_is_out_of_reach(): void
    {
        $foreign = EventCategory::factory()->create(['name' => 'Fremd']);

        Livewire::test(Categories::class)
            ->call('rename', $foreign->id, 'Meins')->call('togglePomodoro', $foreign->id)->call('delete', $foreign->id);

        $this->assertSame('Fremd', $foreign->fresh()->name);
        $this->assertNotSoftDeleted($foreign);
    }
}
