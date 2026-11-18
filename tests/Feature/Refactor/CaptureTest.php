<?php

namespace Tests\Feature\Refactor;

use App\Models\Task;
use App\Models\User;
use App\Support\NarrowAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A8 (R3, risk C10): capture saves a 400 character title, rejects an empty one
 * with a German message, refuses a token without the capture ability. The
 * narrow capture token reaches nothing else.
 */
class CaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('iPhone: In die Inbox', [NarrowAbility::CAPTURE])->plainTextToken;
    }

    public function test_a_400_character_title_with_umlauts_is_saved_whole_in_the_inbox(): void
    {
        $title = str_repeat('Grösse prüfen, Öl über Ähre ', 15).'Ende';
        $this->assertGreaterThanOrEqual(400, mb_strlen($title));

        $this->withToken($this->token)->postJson('/api/capture', ['text' => $title])
            ->assertCreated()
            ->assertJsonPath('list', 'inbox')
            ->assertJsonPath('title', $title)
            ->assertJsonPath('message', 'In der Inbox.');

        $task = Task::sole();
        $this->assertSame($title, $task->title);
        $this->assertSame('inbox', $task->list);
        $this->assertSame($this->user->id, $task->user_id);
    }

    public function test_an_empty_capture_is_refused_with_a_german_message(): void
    {
        foreach (['', '   ', "\n\t"] as $text) {
            $this->withToken($this->token)->postJson('/api/capture', ['text' => $text])
                ->assertUnprocessable()
                ->assertJsonPath('errors.text.0', 'Da steht noch nichts. Schreib oder diktier einen Gedanken.');
        }
        $this->withToken($this->token)->postJson('/api/capture', [])->assertUnprocessable();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_too_long_text_is_refused_calmly(): void
    {
        $this->withToken($this->token)->postJson('/api/capture', ['text' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.text.0', 'Das ist zu lang. Höchstens 500 Zeichen, den Rest kannst du später in die Notizen schreiben.');
    }

    public function test_dictated_whitespace_collapses_to_one_line(): void
    {
        $this->withToken($this->token)->postJson('/api/capture', ['text' => "  Milch\n  kaufen  "])->assertCreated();

        $this->assertSame('Milch kaufen', Task::sole()->title);
    }

    public function test_new_captures_go_to_the_end_of_the_inbox(): void
    {
        Task::factory()->for($this->user)->create(['list' => 'inbox', 'sort_order' => 4]);

        $this->withToken($this->token)->postJson('/api/capture', ['text' => 'Neu'])->assertCreated();

        $this->assertSame(5, Task::query()->where('title', 'Neu')->sole()->sort_order);
    }

    public function test_a_token_without_the_capture_ability_is_refused(): void
    {
        $mcp = $this->user->createToken('mcp', ['mcp:read'])->plainTextToken;
        $arrival = $this->user->createToken('arrival', [NarrowAbility::ARRIVAL])->plainTextToken;

        foreach ([$mcp, $arrival] as $token) {
            $this->withToken($token)->postJson('/api/capture', ['text' => 'Nein'])
                ->assertForbidden()
                ->assertJsonPath('message', 'Dieses Token darf nichts in die Inbox legen.');
        }
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_a_full_token_may_capture_too(): void
    {
        $full = $this->user->createToken('Shortcuts')->plainTextToken;

        $this->withToken($full)->postJson('/api/capture', ['text' => 'Mit vollem Token'])->assertCreated();
    }

    public function test_without_a_token_nothing_happens(): void
    {
        $this->postJson('/api/capture', ['text' => 'Anonym'])->assertUnauthorized();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_the_capture_token_reaches_nothing_else(): void
    {
        Task::factory()->for($this->user)->create();

        $this->withToken($this->token)->getJson('/api/tasks')->assertForbidden();
        $this->withToken($this->token)->getJson('/api/suggestion')->assertForbidden();
        $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
        $this->withToken($this->token)->postJson('/api/arrival')->assertForbidden();
    }

    public function test_capture_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withToken($this->token)->postJson('/api/capture', ['text' => "Idee {$i}"])->assertCreated();
        }

        $this->withToken($this->token)->postJson('/api/capture', ['text' => 'Eine zu viel'])->assertTooManyRequests();
    }

    public function test_the_capture_token_command_prints_a_narrow_token(): void
    {
        $this->artisan('app:capture-token', ['email' => $this->user->email])->assertSuccessful();

        $token = $this->user->tokens()->latest('id')->first();
        $this->assertSame([NarrowAbility::CAPTURE], $token->abilities);

        $this->artisan('app:capture-token', ['email' => 'niemand@example.com'])->assertFailed();
    }
}
