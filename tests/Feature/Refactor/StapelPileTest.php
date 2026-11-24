<?php

namespace Tests\Feature\Refactor;

use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Stapel piles are 2 × 2 cells of the mobile board. A pile is a <button>, and a button shrinks to its
 * content inside a block parent, which squeezed the card to ~56 px at 390 px (titles broke mid-word,
 * "Wichtig" showed as "Wich…"). These tests pin the markup that keeps the card as wide as its cell.
 */
class StapelPileTest extends TestCase
{
    use RefreshDatabase;

    private function pile(string $list, ?Task $top, int $count): string
    {
        app()->setLocale('de');

        return view('shell.pile', ['list' => $list, 'top' => $top, 'count' => $count, 'mode' => 'board'])->render();
    }

    private function task(string $title, string $list, bool $important = false): Task
    {
        return Task::forceCreate([
            'user_id' => User::factory()->create()->id, 'title' => $title, 'list' => $list,
            'size' => Size::Short, 'is_important' => $important,
        ]);
    }

    public function test_every_kind_of_pile_fills_its_grid_cell(): void
    {
        $task = $this->task('Mathe-Aufgaben Kapitel 4', 'todos');

        foreach (['todos' => $this->pile('todos', $task, 2), 'later' => $this->pile('later', $task, 2), 'done' => $this->pile('done', $task, 2)] as $list => $html) {
            $this->assertStringContainsString('group flex w-full min-w-0 flex-col', $html, "the $list pile must be w-full");
        }
    }

    public function test_the_title_wraps_on_whole_words_and_the_flag_is_not_part_of_the_title(): void
    {
        $html = $this->pile('tasks', $this->task('Wettkampfanmeldung absenden', 'tasks', true), 2);

        preg_match('~<b class="line-clamp-2[^"]*"[^>]*>(.*?)</b>~s', $html, $title);

        $this->assertNotEmpty($title, 'the title element is there');
        $this->assertStringNotContainsString('<svg', $title[1], 'the flag would take 18 px from a long word');
        $this->assertStringContainsString('Wettkampfanmeldung absenden', $title[1]);
        $this->assertStringContainsString('hyphens-auto', $html, 'long German compounds hyphenate where the browser can');
    }

    public function test_a_marked_top_card_keeps_flag_and_the_word_wichtig(): void
    {
        $html = $this->pile('todos', $this->task('Zahnarzt anrufen', 'todos', true), 1);

        $this->assertMatchesRegularExpression('~<svg.*?</svg>\s*<span class="truncate">Wichtig · ~s', $html);
    }

    public function test_an_unmarked_top_card_has_no_flag_and_an_empty_pile_has_no_meta_line(): void
    {
        $this->assertStringNotContainsString('<svg', $this->pile('todos', $this->task('Brot kaufen', 'todos'), 1));
        $this->assertStringNotContainsString('<small', $this->pile('todos', null, 0));
    }
}
