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

    public function test_settings_show_pomodoro_and_categories(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Pomodoro')
            ->assertSee('Kategorien');
    }

    public function test_it_saves_pomodoro_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('pWork', 50)
            ->set('pShortBreak', 10)
            ->set('pLongBreak', 20)
            ->set('pLongEvery', 3)
            ->set('pAutostart', true)
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pomodoro_work' => 50,
            'pomodoro_short_break' => 10,
            'pomodoro_long_break' => 20,
            'pomodoro_long_every' => 3,
            'pomodoro_autostart' => true,
        ]);
    }

    public function test_it_loads_the_saved_autostart_setting_on_mount(): void
    {
        $user = User::factory()->create(['pomodoro_autostart' => true]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->assertSet('pAutostart', true);
    }

    public function test_it_rejects_non_positive_pomodoro_values(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->set('pWork', 0)
            ->set('pLongEvery', -1)
            ->call('saveSchedule')
            ->assertHasErrors(['pWork', 'pLongEvery']);
    }

    public function test_it_accepts_any_positive_pomodoro_value(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('pWork', 1)
            ->set('pShortBreak', 1)
            ->set('pLongBreak', 1)
            ->set('pLongEvery', 99)
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pomodoro_work' => 1,
            'pomodoro_short_break' => 1,
            'pomodoro_long_break' => 1,
            'pomodoro_long_every' => 99,
        ]);
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
            'pomodoro_enabled' => false,
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

    public function test_it_toggles_the_pomodoro_flag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);

        Livewire::test(Settings::class)->call('toggleCategoryPomodoro', $category->id);
        $this->assertTrue($category->refresh()->pomodoro_enabled);

        Livewire::test(Settings::class)->call('toggleCategoryPomodoro', $category->id);
        $this->assertFalse($category->refresh()->pomodoro_enabled);
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

    public function test_it_toggles_the_event_start_notification_setting(): void
    {
        $user = User::factory()->create(['notify_event_start' => false]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('toggleNotifyEventStart');
        $this->assertTrue($user->refresh()->notify_event_start);

        Livewire::test(Settings::class)->call('toggleNotifyEventStart');
        $this->assertFalse($user->refresh()->notify_event_start);
    }

    public function test_it_toggles_the_pomo_start_notification_setting(): void
    {
        $user = User::factory()->create(['notify_pomo_start' => false]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('toggleNotifyPomoStart');
        $this->assertTrue($user->refresh()->notify_pomo_start);
    }

    public function test_it_toggles_the_break_start_notification_setting(): void
    {
        $user = User::factory()->create(['notify_break_start' => false]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('toggleNotifyBreakStart');
        $this->assertTrue($user->refresh()->notify_break_start);
    }
}
