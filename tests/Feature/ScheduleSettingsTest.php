<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_categories(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Kategorien');
    }

    public function test_it_saves_a_fractional_timezone_offset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('timezoneOffset', 5.5)
            ->call('saveTimezone')
            ->assertHasNoErrors();

        $this->assertEquals(5.5, $user->refresh()->timezone_offset);
        $this->assertSame(330, $user->utcOffsetMinutes());
    }

    public function test_it_adds_a_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('newCategoryName', 'Lesen')
            ->set('newCategoryColor', 'forest')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('event_categories', [
            'user_id' => $user->id,
            'name' => 'Lesen',
            'color' => 'forest',
        ]);
    }

    public function test_it_renames_a_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['name' => 'Schule']);

        Livewire::test(Settings::class)->call('renameCategory', $category->id, 'Uni');

        $this->assertSame('Uni', $category->refresh()->name);
    }

    public function test_it_sets_a_category_color(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['color' => 'contour']);

        Livewire::test(Settings::class)->call('setCategoryColor', $category->id, 'signal');

        $this->assertSame('signal', $category->refresh()->color);
    }

    public function test_it_rejects_an_invalid_category_color(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['color' => 'contour']);

        Livewire::test(Settings::class)->call('setCategoryColor', $category->id, 'not-a-color');

        $this->assertSame('contour', $category->refresh()->color);
    }

    public function test_it_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create();

        Livewire::test(Settings::class)->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('event_categories', ['id' => $category->id]);
    }

    public function test_a_user_cannot_touch_another_users_category(): void
    {
        $this->actingAs(User::factory()->create());
        $other = EventCategory::factory()->for(User::factory())->create(['name' => 'Original', 'color' => 'contour']);

        Livewire::test(Settings::class)->call('renameCategory', $other->id, 'Hijacked');
        Livewire::test(Settings::class)->call('setCategoryColor', $other->id, 'signal');
        Livewire::test(Settings::class)->call('deleteCategory', $other->id);

        $this->assertSame('Original', $other->refresh()->name);
        $this->assertSame('contour', $other->color);
        $this->assertDatabaseHas('event_categories', ['id' => $other->id]);
    }
}
