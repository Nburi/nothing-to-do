<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\CaptureTokens;
use App\Models\Task;
use App\Models\User;
use App\Support\NarrowAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2.3: the capture line on every page of the new app, its undo, and the settings card that
 * creates and revokes the narrow capture token (risk C10).
 */
class ShellCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = User::factory()->create();
    }

    public function test_the_line_saves_into_the_inbox(): void
    {
        $title = str_repeat('Größere Überlegung ', 21); // 399 characters with umlauts

        $this->actingAs($this->user)->postJson('/board/capture', ['text' => "  {$title}  "])
            ->assertCreated()
            ->assertJson(['title' => trim($title), 'inbox' => 1, 'message' => 'In der Inbox.']);

        $this->assertSame('inbox', Task::where('user_id', $this->user->id)->first()->list);
    }

    public function test_an_empty_line_is_refused_with_a_german_message(): void
    {
        $this->actingAs($this->user)->postJson('/board/capture', ['text' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('errors.text.0', 'Da steht noch nichts. Schreib oder diktier einen Gedanken.');

        $this->assertSame(0, Task::count());
    }

    public function test_guests_cannot_capture(): void
    {
        $this->postJson('/board/capture', ['text' => 'x'])->assertUnauthorized();
    }

    public function test_undo_takes_a_fresh_capture_back_but_nothing_else(): void
    {
        $id = $this->actingAs($this->user)->postJson('/board/capture', ['text' => 'Velo-Licht'])->json('id');

        $this->deleteJson("/board/capture/{$id}")->assertOk()->assertJson(['message' => 'Zurückgenommen.']);
        $this->assertNull(Task::find($id));

        $old = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Alt', 'list' => 'inbox']);
        $old->forceFill(['created_at' => now()->subHour()])->save();
        $sorted = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Sortiert', 'list' => 'todos']);
        $foreign = Task::forceCreate(['user_id' => User::factory()->create()->id, 'title' => 'Fremd', 'list' => 'inbox']);

        foreach ([$old, $sorted, $foreign] as $task) {
            $this->deleteJson("/board/capture/{$task->id}")->assertNotFound();
            $this->assertNotNull($task->fresh());
        }
    }

    public function test_every_page_of_the_new_app_has_the_capture_line(): void
    {
        foreach (['/board', '/board/settings'] as $page) {
            $this->actingAs($this->user)->get($page)
                ->assertOk()
                ->assertSee('id="capture-config"', false)
                ->assertSee('data-capture-inline', false)
                ->assertSee('Was geht dir durch den Kopf?');
        }
    }

    public function test_a_token_is_created_shown_once_and_can_only_capture(): void
    {
        $card = Livewire::actingAs($this->user)->test(CaptureTokens::class)
            ->assertSee('Noch kein Token.')
            ->set('name', 'iPhone')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Token „iPhone“ erstellt.');

        $plain = $card->get('plain');
        $this->assertNotNull($plain);
        $token = PersonalAccessToken::findToken($plain);
        $this->assertSame([NarrowAbility::CAPTURE], $token->abilities);

        $card->call('dismiss')->assertSet('plain', null)->assertDontSee($plain);

        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->postJson('/api/capture', ['text' => 'Diktiert'])->assertCreated();
        $this->withToken($plain)->getJson('/api/tasks')->assertForbidden();
    }

    public function test_a_token_needs_a_name(): void
    {
        Livewire::actingAs($this->user)->test(CaptureTokens::class)
            ->set('name', '  ')
            ->call('create')
            ->assertHasErrors('name')
            ->assertSee('Gib dem Token einen Namen, zum Beispiel iPhone.');

        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_revoking_asks_once_inline_then_the_token_stops_working(): void
    {
        $plain = $this->user->createToken('iPad', [NarrowAbility::CAPTURE])->plainTextToken;
        $id = PersonalAccessToken::findToken($plain)->id;

        Livewire::actingAs($this->user)->test(CaptureTokens::class)
            ->assertSee('iPad')
            ->call('askRevoke', $id)
            ->assertSee('hört sofort auf zu funktionieren')
            ->call('keep')
            ->assertDontSee('hört sofort auf zu funktionieren')
            ->call('askRevoke', $id)
            ->call('revoke', $id)
            ->assertSee('Token „iPad“ widerrufen.');

        $this->assertNull(PersonalAccessToken::find($id));
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->postJson('/api/capture', ['text' => 'x'])->assertUnauthorized();
    }

    public function test_full_api_tokens_and_other_users_tokens_are_not_listed_or_revocable(): void
    {
        $this->user->createToken('MCP', ['*']);
        $other = User::factory()->create();
        $foreignPlain = $other->createToken('Fremd', [NarrowAbility::CAPTURE])->plainTextToken;
        $foreignId = PersonalAccessToken::findToken($foreignPlain)->id;

        Livewire::actingAs($this->user)->test(CaptureTokens::class)
            ->assertDontSee('MCP')
            ->assertDontSee('Fremd')
            ->call('revoke', $foreignId)
            ->assertSee('Dieses Token gibt es nicht mehr.');

        $this->assertNotNull(PersonalAccessToken::find($foreignId));
    }
}
