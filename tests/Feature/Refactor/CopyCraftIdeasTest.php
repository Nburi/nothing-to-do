<?php

namespace Tests\Feature\Refactor;

use App\Models\CraftIdea;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 3.4 (R6, R8): `app:copy-craft-ideas` copies every old Bastelidee once into Later with its
 * "where to begin" note. Non-destructive and idempotent; meant for the switch (5.4).
 */
class CopyCraftIdeasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-16 10:00', 'UTC'));
        $this->user = User::factory()->create();
    }

    private function idea(string $title, ?string $begin = null, bool $done = false, ?User $user = null, string $created = '2026-09-01 12:00'): CraftIdea
    {
        $idea = CraftIdea::factory()->for($user ?? $this->user)->create(['title' => $title, 'where_to_begin' => $begin, 'is_done' => $done]);
        $idea->forceFill(['created_at' => $created, 'updated_at' => '2026-10-02 08:00'])->saveQuietly();

        return $idea->fresh();
    }

    /** @return list<array<string, mixed>> */
    private function craftRows(): array
    {
        return DB::table('craft_ideas')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function test_every_idea_becomes_a_later_task_with_its_where_to_begin_note(): void
    {
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Schon in Später', 'list' => 'later', 'sort_order' => 7]);
        $this->idea('Kompass-App skizzieren', 'Drei Screens auf Papier', created: '2026-08-10 09:00');
        $this->idea('Fotoalbum Sommer', null, created: '2026-08-20 09:00');
        $this->idea('Vogelhaus bauen', 'Holz kaufen', done: true, created: '2026-08-30 09:00');
        $before = $this->craftRows();

        $this->artisan('app:copy-craft-ideas')->assertSuccessful()->expectsOutputToContain('craft_ideas was not changed.');

        $copies = Task::where('user_id', $this->user->id)->where('title', '!=', 'Schon in Später')->orderBy('sort_order')->get();
        $this->assertSame(['Kompass-App skizzieren', 'Fotoalbum Sommer', 'Vogelhaus bauen'], $copies->pluck('title')->all());
        $this->assertSame(['later', 'later', 'later'], $copies->pluck('list')->all());
        $this->assertSame([8, 9, 10], $copies->pluck('sort_order')->all(), 'after the existing Later items');
        $this->assertSame('Drei Screens auf Papier', $copies[0]->first_step);
        $this->assertNull($copies[1]->first_step);
        $this->assertSame([false, false, true], $copies->pluck('is_completed')->all());
        $this->assertSame('2026-10-02 08:00', $copies[2]->completed_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-10 09:00', $copies[0]->created_at->format('Y-m-d H:i'));

        $this->assertSame($before, $this->craftRows(), 'craft_ideas is never changed');
        $this->assertSame(3, DB::table('craft_idea_copies')->count());
    }

    public function test_a_second_run_copies_nothing_twice_but_picks_up_new_ideas(): void
    {
        $this->idea('Kompass-App skizzieren');
        $this->artisan('app:copy-craft-ideas')->assertSuccessful();

        $this->artisan('app:copy-craft-ideas')->assertSuccessful();
        $this->assertSame(1, Task::where('title', 'Kompass-App skizzieren')->count());

        $this->idea('Fotoalbum Sommer');
        $this->artisan('app:copy-craft-ideas')->assertSuccessful();
        $this->assertSame(1, Task::where('title', 'Kompass-App skizzieren')->count());
        $this->assertSame(1, Task::where('title', 'Fotoalbum Sommer')->count());
    }

    public function test_a_copied_task_the_user_deleted_is_not_brought_back(): void
    {
        $this->idea('Kompass-App skizzieren');
        $this->artisan('app:copy-craft-ideas')->assertSuccessful();
        Task::where('title', 'Kompass-App skizzieren')->delete();

        $this->artisan('app:copy-craft-ideas')->assertSuccessful();

        $this->assertSame(0, Task::where('title', 'Kompass-App skizzieren')->count());
        $this->assertSame(1, CraftIdea::count(), 'the craft idea itself stays');
    }

    public function test_dry_run_writes_nothing_and_reports_what_it_would_copy(): void
    {
        $this->idea('Kompass-App skizzieren');
        $this->idea('Fotoalbum Sommer');

        $this->artisan('app:copy-craft-ideas', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('dry run, nothing is written')
            ->expectsTable(['users', 'craft ideas', 'already copied', 'would copy', 'long notes kept in notes'], [[1, 2, 0, 2, 0]]);

        $this->assertSame(0, Task::count());
        $this->assertSame(0, DB::table('craft_idea_copies')->count());
    }

    public function test_a_long_where_to_begin_is_kept_whole_in_the_notes(): void
    {
        $long = str_repeat('Erst die Bretter zuschneiden, dann schleifen. ', 10); // > 255 characters
        $this->idea('Vogelhaus bauen', $long);

        $this->artisan('app:copy-craft-ideas')->assertSuccessful();

        $task = Task::where('title', 'Vogelhaus bauen')->sole();
        $this->assertLessThanOrEqual(255, mb_strlen($task->first_step));
        $this->assertStringEndsWith('…', $task->first_step);
        $this->assertSame(trim($long), $task->notes);
    }

    public function test_only_one_user_with_the_user_option(): void
    {
        $other = User::factory()->create();
        $this->idea('Meine Idee');
        $this->idea('Fremde Idee', user: $other);

        $this->artisan('app:copy-craft-ideas', ['--user' => $this->user->id])->assertSuccessful();

        $this->assertSame(1, Task::where('user_id', $this->user->id)->count());
        $this->assertSame(0, Task::where('user_id', $other->id)->count());
        $this->artisan('app:copy-craft-ideas', ['--user' => 'niels'])->assertFailed();
    }

    public function test_copies_show_up_in_the_ideas_view(): void
    {
        $this->idea('Kompass-App skizzieren', 'Drei Screens auf Papier');
        $this->artisan('app:copy-craft-ideas')->assertSuccessful();

        $this->actingAs($this->user)->get('/board/ideas')->assertOk()->assertSee(['Kompass-App skizzieren', 'Drei Screens auf Papier']);
    }
}
