<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 5.2 (risk C7): the shell pages must not run more queries the more tasks an account has (no N+1), and
 * stay under a ceiling. The ceilings are what the pages need today with some room; a page that goes over
 * should be looked at, not have its ceiling raised.
 *
 * Measured on 2026-11-25 with 700 tasks: /board 51 queries before the plan was read once per render, 33 after.
 */
class ShellQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5, 6, 7], '06:00', '23:30', $deep->id);
    }

    /** Adds $count tasks per list (with areas, sizes, steps of projects). */
    private function fill(int $count): void
    {
        $areas = collect(['Schule', 'Sport', 'Haushalt'])->map(function (string $name) {
            $area = new Area(['name' => $name, 'color' => 'area-1', 'sort_order' => 0]);
            $area->user_id = $this->user->id;
            $area->save();

            return $area;
        });

        foreach (['inbox', 'todos', 'tasks', 'later'] as $list) {
            for ($i = 0; $i < $count; $i++) {
                Task::forceCreate([
                    'user_id' => $this->user->id, 'title' => "{$list} {$i}", 'list' => $list,
                    'size' => $list === 'inbox' ? null : Size::Short, 'area_id' => $areas[$i % 3]->id,
                    'sort_order' => $i, 'is_important' => $i % 7 === 0,
                ]);
            }
        }

        for ($p = 0; $p < intdiv($count, 4) + 1; $p++) {
            $project = new Project(['name' => "Projekt {$p}"]);
            $project->user_id = $this->user->id;
            $project->save();
            $stage = $project->stages()->create(['name' => 'Planen', 'sort_order' => 0]);

            for ($s = 0; $s < 4; $s++) {
                Task::forceCreate(['user_id' => $this->user->id, 'title' => "P{$p} Schritt {$s}", 'list' => 'tasks', 'project_id' => $project->id, 'stage_id' => $stage->id, 'sort_order' => $s]);
            }
        }
    }

    private function queries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->get($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return iterable<string, array{string, int}> page and its ceiling */
    public static function pages(): iterable
    {
        yield '/board' => ['/board', 40];
        yield '/board/week' => ['/board/week', 20];
        yield '/board/week/plan' => ['/board/week/plan', 20];
        yield '/board/projects' => ['/board/projects', 20];
        yield '/board/ideas' => ['/board/ideas', 25];
        yield '/board/settings' => ['/board/settings', 20];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_a_page_runs_the_same_queries_with_ten_tasks_and_with_two_hundred(string $uri, int $ceiling): void
    {
        // Warm up: the first request of a process loads config, locale and the like.
        $this->fill(10);
        $this->queries($uri);

        $small = $this->queries($uri);
        $this->fill(50);
        $large = $this->queries($uri);

        $this->assertSame($small, $large, "{$uri}: {$small} queries with 10 tasks per list, {$large} with 60 (an N+1)");
        $this->assertLessThanOrEqual($ceiling, $large, "{$uri} needs {$large} queries, the budget is {$ceiling}");
    }

    public function test_the_board_reads_the_plan_once_per_render(): void
    {
        $this->fill(5);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->get('/board')->assertOk();
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        // The week plan (templates, events, pauses; the evening reset's own "is today paused" exists-query is another thing) was read four times before step 5.2: pick, idea, crunch, time line.
        $this->assertSame(1, count(array_filter($sql, fn (string $q) => str_contains($q, 'from "event_templates"'))), 'event_templates');
        $this->assertSame(1, count(array_filter($sql, fn (string $q) => str_starts_with($q, 'select * from "schedule_pauses"'))), 'schedule_pauses');
        $this->assertSame(1, count(array_filter($sql, fn (string $q) => str_contains($q, 'from "tasks" where "user_id" = ? and "is_completed" = ? and "list" in'))), 'the open work tasks');
    }
}
