<?php

namespace Tests\Unit;

use App\Models\ScheduleEvent;
use App\Services\ScheduleGenerator;
use Tests\TestCase;

class ScheduleGeneratorTest extends TestCase
{
    private function gen(): ScheduleGenerator
    {
        return new ScheduleGenerator(work: 25, shortBreak: 5, longBreak: 15, longEvery: 4);
    }

    private function work(int $n): array
    {
        return array_fill(0, $n, ['kind' => 'work', 'task_id' => null]);
    }

    public function test_places_sessions_with_short_breaks_and_no_trailing_break(): void
    {
        $result = $this->gen()->generate([['start' => 0, 'end' => 120]], $this->work(3));

        $this->assertSame(3, $result['placed']);
        $this->assertSame(0, $result['unplaced']);

        $types = array_column($result['events'], 'type');
        $this->assertSame([
            ScheduleEvent::TYPE_WORK, ScheduleEvent::TYPE_BREAK,
            ScheduleEvent::TYPE_WORK, ScheduleEvent::TYPE_BREAK,
            ScheduleEvent::TYPE_WORK,
        ], $types); // ends on a session — never a trailing break

        $this->assertSame('00:00', $result['events'][0]['start_time']);
        $this->assertSame('00:25', $result['events'][0]['end_time']);
        $this->assertSame('00:30', $result['events'][2]['start_time']);
    }

    public function test_inserts_a_long_break_after_every_fourth_session(): void
    {
        $events = $this->gen()->generate([['start' => 0, 'end' => 600]], $this->work(5))['events'];

        $breaks = array_values(array_filter($events, fn ($e) => $e['type'] === ScheduleEvent::TYPE_BREAK));

        // 4 sessions then a break → that 4th-position break is the long one (15').
        $this->assertCount(4, $breaks);
        $longest = $breaks[3];
        $start = ScheduleEvent::toMinutes($longest['start_time']);
        $end = ScheduleEvent::toMinutes($longest['end_time']);
        $this->assertSame(15, $end - $start);
    }

    public function test_a_session_never_crosses_a_block_boundary_and_uses_the_next_block(): void
    {
        $result = $this->gen()->generate(
            [['start' => 0, 'end' => 40], ['start' => 100, 'end' => 160]],
            $this->work(3),
        );

        $sessions = array_values(array_filter($result['events'], fn ($e) => $e['type'] === ScheduleEvent::TYPE_WORK));

        $this->assertSame(3, $result['placed']);
        $this->assertSame('00:00', $sessions[0]['start_time']);
        $this->assertSame('00:25', $sessions[0]['end_time']);  // fits in block 1
        $this->assertSame('01:40', $sessions[1]['start_time']); // jumps to block 2 (100')
    }

    public function test_capacity_matches_what_generate_actually_places(): void
    {
        $blocks = [['start' => 0, 'end' => 150]];
        $capacity = $this->gen()->capacity($blocks);

        $this->assertSame($capacity, $this->gen()->generate($blocks, $this->work($capacity))['placed']);
        $this->assertSame(1, $this->gen()->generate($blocks, $this->work($capacity + 1))['unplaced']);
    }

    public function test_todo_unit_becomes_a_todo_session_and_work_unit_carries_its_suggestion(): void
    {
        $events = $this->gen()->generate(
            [['start' => 0, 'end' => 120]],
            [['kind' => 'todo'], ['kind' => 'work', 'task_id' => 7]],
        )['events'];

        $this->assertSame(ScheduleEvent::TYPE_TODO, $events[0]['type']);
        $this->assertSame('To-Do-Session', $events[0]['title']);
        $this->assertNull($events[0]['suggested_task_id']);

        $work = end($events);
        $this->assertSame(ScheduleEvent::TYPE_WORK, $work['type']);
        $this->assertSame(7, $work['suggested_task_id']);
    }

    public function test_blocks_too_short_for_a_session_are_ignored(): void
    {
        $result = $this->gen()->generate([['start' => 0, 'end' => 20]], $this->work(2));

        $this->assertSame(0, $result['placed']);
        $this->assertSame(2, $result['unplaced']);
        $this->assertSame([], $result['events']);
    }
}
