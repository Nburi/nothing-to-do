<?php

namespace Tests\Feature\Refactor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A56 (step 4.3b, R8): the old notes come back as note cards. A project's brainstorm becomes its
 * first card (no stage), a group's note cards become its cards, tags are read from the text.
 * Nothing is deleted (brainstorm and group_notes stay), it runs twice without doubling and rolls back.
 */
class NotesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_000002_copy_old_notes_into_note_cards.php');
    }

    private function row(string $table, array $values): int
    {
        return DB::table($table)->insertGetId([...$values, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_brainstorm_and_group_notes_become_note_cards_with_tags(): void
    {
        $user = User::factory()->create();
        $project = $this->row('projects', ['user_id' => $user->id, 'name' => 'Enderdragon', 'brainstorm' => "Fabric-Server #Idee\n\n- Lifesteal #bug", 'sort_order' => 0]);
        $empty = $this->row('projects', ['user_id' => $user->id, 'name' => 'Leer', 'brainstorm' => "  \n", 'sort_order' => 1]);
        $group = $this->row('task_groups', ['user_id' => $user->id, 'name' => 'Notion', 'list' => 'tasks', 'sort_order' => 0]);
        $this->row('group_notes', ['task_group_id' => $group, 'content' => 'Zweite', 'sort_order' => 1]);
        $this->row('group_notes', ['task_group_id' => $group, 'content' => 'Erste #link', 'sort_order' => 0]);

        $this->migration()->up();

        $card = DB::table('notes')->where('project_id', $project)->first();
        $this->assertSame("Fabric-Server #Idee\n\n- Lifesteal #bug", $card->body);
        $this->assertNull($card->stage_id, 'the brainstorm lands in "Lose"');
        $this->assertSame($user->id, $card->user_id);
        $this->assertSame(0, DB::table('notes')->where('project_id', $empty)->count(), 'an empty brainstorm makes no card');

        $this->assertSame(['Erste #link', 'Zweite'], DB::table('notes')->where('group_id', $group)->orderBy('sort_order')->pluck('body')->all());

        $tags = DB::table('tags')->where('user_id', $user->id)->orderBy('name')->pluck('name')->all();
        $this->assertSame(['bug', 'idee', 'link'], $tags, 'tags are lower case and shared per user');
        $this->assertSame(2, DB::table('note_tag')->where('note_id', $card->id)->count());

        $this->assertSame("Fabric-Server #Idee\n\n- Lifesteal #bug", DB::table('projects')->where('id', $project)->value('brainstorm'), 'brainstorm stays');
        $this->assertSame(2, DB::table('group_notes')->count(), 'group notes stay');
    }

    public function test_notes_of_a_group_that_became_a_project_are_not_copied_twice(): void
    {
        $user = User::factory()->create();
        $project = $this->row('projects', ['user_id' => $user->id, 'name' => 'Zimmer', 'brainstorm' => 'Kartons besorgen', 'sort_order' => 0]);
        $group = $this->row('task_groups', ['user_id' => $user->id, 'project_id' => $project, 'name' => 'Zimmer', 'sort_order' => 0]);
        $this->row('group_notes', ['task_group_id' => $group, 'content' => 'Kartons besorgen', 'sort_order' => 0]);

        $this->migration()->up();

        $this->assertSame(['Kartons besorgen'], DB::table('notes')->pluck('body')->all());
        $this->assertSame($project, DB::table('notes')->value('project_id'));
    }

    public function test_it_runs_twice_without_doubling_and_rolls_back_only_what_it_made(): void
    {
        $user = User::factory()->create();
        $project = $this->row('projects', ['user_id' => $user->id, 'name' => 'P', 'brainstorm' => 'Idee #a', 'sort_order' => 0]);
        $own = $this->row('notes', ['user_id' => $user->id, 'project_id' => $project, 'body' => 'Neu geschrieben', 'sort_order' => 5]);

        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(2, DB::table('notes')->count());

        $this->migration()->down();
        $this->assertSame([$own], DB::table('notes')->pluck('id')->all(), 'a card written by hand stays');
        $this->assertSame('Idee #a', DB::table('projects')->value('brainstorm'));
    }
}
