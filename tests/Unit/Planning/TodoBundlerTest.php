<?php

namespace Tests\Unit\Planning;

use App\Planning\Pick\Pick;
use App\Planning\Pick\Reason;
use App\Planning\Pick\TodoBundler;
use PHPUnit\Framework\TestCase;

/** To-do session (2026-11-24): the most urgent To-dos that fit are filled up to 25 minutes. */
class TodoBundlerTest extends TestCase
{
    private function pick(int $id, int $minutes): Pick
    {
        return new Pick($id, Reason::NoDeadline, $minutes);
    }

    private function ids(array $picks): array
    {
        return array_map(fn (Pick $p) => $p->taskId, $picks);
    }

    public function test_fills_up_to_25_minutes_in_priority_order(): void
    {
        $session = (new TodoBundler)->bundle([$this->pick(1, 10), $this->pick(2, 10), $this->pick(3, 10)]);

        $this->assertSame([1, 2], $this->ids($session));
    }

    public function test_a_todo_that_does_not_fit_is_skipped_and_a_smaller_later_one_is_taken(): void
    {
        $session = (new TodoBundler)->bundle([$this->pick(1, 20), $this->pick(2, 10), $this->pick(3, 5)]);

        $this->assertSame([1, 3], $this->ids($session));
    }

    public function test_the_most_urgent_todo_always_goes_in_even_when_it_is_bigger_than_a_session(): void
    {
        $session = (new TodoBundler)->bundle([$this->pick(1, 75), $this->pick(2, 10)]);

        $this->assertSame([1], $this->ids($session));
    }

    public function test_no_todos_make_no_session(): void
    {
        $this->assertSame([], (new TodoBundler)->bundle([]));
    }
}
