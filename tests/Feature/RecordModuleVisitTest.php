<?php

namespace Tests\Feature;

use App\Models\ModuleVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The App\Http\Middleware\RecordModuleVisit middleware, registered globally
 * on the 'web' group in bootstrap/app.php — see that middleware's own
 * docblock for why it's global rather than attached per-route.
 */
class RecordModuleVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_a_scopable_modules_page_records_a_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('schedule'))->assertOk();

        $this->assertDatabaseHas('module_visits', [
            'user_id' => $user->id,
            'module_key' => 'schedule',
        ]);
    }

    public function test_visiting_planer_records_a_visit_even_though_it_is_not_in_app_modules_catalog(): void
    {
        $user = User::factory()->create(['planner_enabled' => true]);

        $this->actingAs($user)->get(route('planner'))->assertOk();

        $this->assertDatabaseHas('module_visits', [
            'user_id' => $user->id,
            'module_key' => 'planner',
        ]);
    }

    public function test_visiting_the_board_does_not_record_a_module_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app'))->assertOk();

        $this->assertDatabaseCount('module_visits', 0);
    }

    public function test_visiting_settings_does_not_record_a_module_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings'))->assertOk();

        $this->assertDatabaseCount('module_visits', 0);
    }

    public function test_a_guest_request_records_nothing_and_does_not_error(): void
    {
        $this->get(route('home'))->assertOk();

        $this->assertDatabaseCount('module_visits', 0);
    }

    public function test_visiting_the_same_module_twice_updates_the_existing_row_instead_of_duplicating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('agenda'))->assertOk();
        $this->actingAs($user)->get(route('agenda'))->assertOk();

        $this->assertSame(1, ModuleVisit::query()
            ->where('user_id', $user->id)
            ->where('module_key', 'agenda')
            ->count());
    }

    public function test_two_different_users_visiting_the_same_module_each_get_their_own_row(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->get(route('crafts'))->assertOk();
        $this->actingAs($userB)->get(route('crafts'))->assertOk();

        $this->assertSame(2, ModuleVisit::query()->where('module_key', 'crafts')->count());
    }

    public function test_visiting_different_modules_records_separate_rows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('schedule'))->assertOk();
        $this->actingAs($user)->get(route('agenda'))->assertOk();

        $this->assertSame(2, ModuleVisit::query()->where('user_id', $user->id)->count());
    }
}
