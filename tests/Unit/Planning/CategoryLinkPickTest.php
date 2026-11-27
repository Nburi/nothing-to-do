<?php

namespace Tests\Unit\Planning;

use App\Planning\Battery;
use App\Planning\Category;
use App\Planning\Pick\PickCandidate;
use App\Planning\Pick\PickEngine;
use App\Planning\Pick\Reason;
use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Planning\StartBy;
use PHPUnit\Framework\TestCase;

/**
 * Step 5.4 (decision "catlinks"): a category linked to a project or group has its next steps suggested
 * first in its blocks, after deadline work, ahead of „Wichtig", whatever the area.
 */
class CategoryLinkPickTest extends TestCase
{
    private const TODAY = '2026-11-09';

    private const PROJECT = 7;

    private const GROUP = 8;

    /** @param  list<PlannedTask>  $tasks */
    private function ids(array $tasks, ?Category $category, Battery $battery = Battery::Okay): array
    {
        $candidates = array_map(fn (PlannedTask $t, int $i) => new PickCandidate($t, $i), $tasks, array_keys($tasks));

        return array_map(
            fn ($p) => $p->taskId.($p->reason === Reason::Linked ? 'L' : ''),
            (new PickEngine)->rank($candidates, $battery, $category, new StartBy([], []), self::TODAY),
        );
    }

    private function linked(?int $project = null, ?int $group = null): Category
    {
        return new Category(1, pomodoro: true, linkedProjectId: $project, linkedGroupId: $group);
    }

    public function test_a_step_of_the_linked_project_goes_before_a_marked_and_a_plain_task(): void
    {
        $tasks = [
            new PlannedTask(1, Size::Short),
            new PlannedTask(2, Size::Short, important: true),
            new PlannedTask(3, Size::Short, projectId: self::PROJECT),
        ];

        $this->assertSame(['3L', '2', '1'], $this->ids($tasks, $this->linked(project: self::PROJECT)));
        $this->assertSame(['2', '1', '3'], $this->ids($tasks, $this->linked()), 'without a link nothing changes');
        $this->assertSame(['2', '1', '3'], $this->ids($tasks, null), 'outside a block there is no link');
    }

    public function test_a_group_link_works_the_same_and_another_project_does_not_count(): void
    {
        $tasks = [
            new PlannedTask(1, Size::Short, projectId: 99),
            new PlannedTask(2, Size::Short, groupId: self::GROUP),
        ];

        $this->assertSame(['2L', '1'], $this->ids($tasks, $this->linked(group: self::GROUP)));
        $this->assertSame(['1', '2'], $this->ids($tasks, $this->linked(project: self::PROJECT)));
    }

    public function test_a_deadline_still_goes_first_and_a_late_one_too(): void
    {
        $tasks = [
            new PlannedTask(1, Size::Short, projectId: self::PROJECT),
            new PlannedTask(2, Size::Short, hardDeadline: '2026-11-12'),
        ];

        $this->assertSame(['2', '1L'], $this->ids($tasks, $this->linked(project: self::PROJECT)));
    }

    public function test_two_deadlines_on_the_same_date_prefer_the_linked_one(): void
    {
        $tasks = [
            new PlannedTask(1, Size::Short, hardDeadline: '2026-11-12'),
            new PlannedTask(2, Size::Short, hardDeadline: '2026-11-12', projectId: self::PROJECT),
        ];

        $this->assertSame(['2', '1'], $this->ids($tasks, $this->linked(project: self::PROJECT)));
    }

    public function test_the_link_passes_the_area_filter_but_other_tasks_still_obey_it(): void
    {
        $category = new Category(1, pomodoro: true, areaIds: [5], linkedProjectId: self::PROJECT);
        $tasks = [
            new PlannedTask(1, Size::Short, areaId: 6),                       // not an area of this category
            new PlannedTask(2, Size::Short, areaId: 6, projectId: self::PROJECT), // another area, but linked
            new PlannedTask(3, Size::Short, areaId: 5),
        ];

        $this->assertSame(['2L', '3'], $this->ids($tasks, $category));
    }

    public function test_the_link_orders_by_the_board_inside_its_own_tier_and_low_battery_still_warms_up_first(): void
    {
        $tasks = [
            new PlannedTask(1, Size::Short, projectId: self::PROJECT),
            new PlannedTask(2, Size::Short, projectId: self::PROJECT, important: true),
            new PlannedTask(3, Size::Quick),
        ];

        $this->assertSame(['2L', '1L', '3'], $this->ids($tasks, $this->linked(project: self::PROJECT)));
        $this->assertSame('3', $this->ids($tasks, $this->linked(project: self::PROJECT), Battery::Low)[0], 'the warm-up goes first');
    }
}
