<?php

namespace Tests\Feature;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\Reminder;
use App\Models\User;
use App\Services\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** "Nicht vergessen" over the REST API, MCP, the settings tools and the data export. */
class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        Carbon::setTestNow('2026-10-27 12:00:00');

        return User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    private function mcp(User $user, string $tool, array $args = [], array $abilities = [McpAbility::READ, McpAbility::WRITE, McpAbility::DELETE]): array
    {
        return app(McpServer::class)->call($user, fn (string $a) => in_array($a, $abilities, true), $tool, $args);
    }

    // ── REST ──────────────────────────────────────────────────────────

    public function test_post_creates_a_reminder_with_both_pushes_on_by_default(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/reminders', ['title' => 'Turnschuhe', 'remind_on' => '2026-10-28'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Turnschuhe')
            ->assertJsonPath('data.remind_on', '2026-10-28')
            ->assertJsonPath('data.date_label', 'morgen')
            ->assertJsonPath('data.notify_evening_before', true)
            ->assertJsonPath('data.notify_on_day', true)
            ->assertJsonPath('data.acknowledged', false);
    }

    public function test_post_validates(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/reminders', [])->assertStatus(422)->assertJsonValidationErrors(['title', 'remind_on']);
        $this->postJson('/api/reminders', ['title' => 'X', 'remind_on' => '2026-10-01'])->assertStatus(422)->assertJsonValidationErrors('remind_on');
        $this->postJson('/api/reminders', ['title' => 'X', 'remind_on' => 'morgen'])->assertStatus(422);
        $this->assertSame(0, Reminder::count());
    }

    public function test_index_lists_upcoming_only_for_me_and_can_hide_done_ones(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['title' => 'Vorbei', 'remind_on' => '2026-10-20']);
        Reminder::factory()->for($user)->create(['title' => 'Offen', 'remind_on' => '2026-10-30']);
        Reminder::factory()->for($user)->acknowledged()->create(['title' => 'Erledigt', 'remind_on' => '2026-10-29']);
        Reminder::factory()->for(User::factory()->create())->create(['title' => 'Fremd', 'remind_on' => '2026-10-29']);
        Sanctum::actingAs($user);

        $this->getJson('/api/reminders')->assertOk()->assertJsonPath('data.0.title', 'Erledigt')->assertJsonPath('data.1.title', 'Offen')->assertJsonCount(2, 'data');
        $this->getJson('/api/reminders?done=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Offen');
    }

    public function test_patch_edits_ticks_and_reopens(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/reminders/{$r->id}", ['title' => 'Anders', 'notify_on_day' => false])->assertOk()->assertJsonPath('data.title', 'Anders');
        $this->assertFalse($r->fresh()->notify_on_day);

        $this->patchJson("/api/reminders/{$r->id}", ['acknowledged' => true])->assertOk()->assertJsonPath('data.acknowledged', true);
        $this->patchJson("/api/reminders/{$r->id}", ['acknowledged' => false])->assertOk()->assertJsonPath('data.acknowledged', false);

        $this->patchJson("/api/reminders/{$r->id}", ['remind_on' => '2026-10-01'])->assertStatus(422);
    }

    public function test_show_and_delete_and_the_404_for_someone_elses(): void
    {
        $user = $this->user();
        $mine = Reminder::factory()->for($user)->create();
        $theirs = Reminder::factory()->for(User::factory()->create())->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/reminders/{$mine->id}")->assertOk();
        $this->getJson("/api/reminders/{$theirs->id}")->assertNotFound();
        $this->patchJson("/api/reminders/{$theirs->id}", ['title' => 'x'])->assertNotFound();
        $this->deleteJson("/api/reminders/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/reminders/{$mine->id}")->assertNoContent();

        $this->assertNull(Reminder::find($mine->id));
        $this->assertNotNull(Reminder::find($theirs->id));
    }

    public function test_the_api_requires_a_token(): void
    {
        $this->getJson('/api/reminders')->assertUnauthorized();
    }

    // ── MCP ───────────────────────────────────────────────────────────

    public function test_mcp_create_list_update_and_delete(): void
    {
        $user = $this->user();

        $created = $this->mcp($user, 'create_reminder', ['title' => 'Turnschuhe', 'remind_on' => '2026-10-28']);
        $this->assertSame('morgen', $created['date_label']);

        $list = $this->mcp($user, 'list_reminders');
        $this->assertSame('2026-10-27', $list['today']);
        $this->assertCount(1, $list['reminders']);

        $ticked = $this->mcp($user, 'update_reminder', ['id' => $created['id'], 'acknowledged' => true, 'title' => 'Sportsachen']);
        $this->assertTrue($ticked['acknowledged']);
        $this->assertSame('Sportsachen', $ticked['title']);
        $this->assertCount(0, $this->mcp($user, 'list_reminders', ['include_done' => false])['reminders']);

        $this->mcp($user, 'delete_reminder', ['id' => $created['id'], 'confirm_title' => 'Sportsachen']);
        $this->assertSame(0, Reminder::count());
    }

    public function test_mcp_delete_needs_the_exact_title_and_the_delete_ability(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['title' => 'Turnschuhe']);

        try {
            $this->mcp($user, 'delete_reminder', ['id' => $r->id, 'confirm_title' => 'turnschuhe']);
            $this->fail('a wrong confirm_title must be refused');
        } catch (McpToolExecutionException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->mcp($user, 'delete_reminder', ['id' => $r->id, 'confirm_title' => 'Turnschuhe'], [McpAbility::READ, McpAbility::WRITE]);
            $this->fail('without mcp:delete the tool must not exist');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertNotNull(Reminder::find($r->id));
    }

    public function test_mcp_create_validates_and_foreign_ids_are_refused(): void
    {
        $user = $this->user();
        $theirs = Reminder::factory()->for(User::factory()->create())->create(['title' => 'Fremd']);

        try {
            $this->mcp($user, 'create_reminder', ['title' => 'X', 'remind_on' => '2026-01-01']);
            $this->fail('a past day must be refused');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        foreach ([['update_reminder', ['id' => $theirs->id, 'title' => 'x']], ['delete_reminder', ['id' => $theirs->id, 'confirm_title' => 'Fremd']]] as [$tool, $args]) {
            try {
                $this->mcp($user, $tool, $args);
                $this->fail("{$tool} must refuse a foreign reminder");
            } catch (McpToolExecutionException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Fremd', $theirs->fresh()->title);
    }

    public function test_mcp_read_only_token_has_no_write_tools(): void
    {
        $user = $this->user();
        $names = collect(app(McpServer::class)->availableTools($user, fn (string $a) => $a === McpAbility::READ))->map(fn ($t) => $t->name());

        $this->assertTrue($names->contains('list_reminders'));
        $this->assertFalse($names->contains('create_reminder'));
        $this->assertFalse($names->contains('update_reminder'));
        $this->assertFalse($names->contains('delete_reminder'));
    }

    public function test_day_overview_includes_open_reminders_for_today_and_tomorrow(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['title' => 'Turnschuhe', 'remind_on' => '2026-10-28']);
        Reminder::factory()->for($user)->create(['title' => 'Später', 'remind_on' => '2026-11-05']);

        $overview = $this->mcp($user, 'get_day_overview');

        $this->assertSame(['Turnschuhe'], collect($overview['reminders'])->pluck('title')->all());
    }

    public function test_mcp_settings_read_and_write_the_reminder_times(): void
    {
        $user = $this->user();

        $this->mcp($user, 'update_settings', ['reminder_evening_time' => '20:15', 'reminder_morning_time' => '06:30', 'reminder_smart_timing' => false]);

        $fresh = $user->fresh();
        $this->assertSame('20:15', $fresh->reminder_evening_time);
        $this->assertFalse((bool) $fresh->reminder_smart_timing);

        $notifications = $this->mcp($user->fresh(), 'get_settings')['notifications'];
        $this->assertSame('20:15', $notifications['reminder_evening_time']);
        $this->assertSame('06:30', $notifications['reminder_morning_time']);
        $this->assertFalse($notifications['reminder_smart_timing']);
    }

    // ── Export ────────────────────────────────────────────────────────

    public function test_the_export_contains_only_my_reminders(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['title' => 'Meine', 'remind_on' => '2026-10-28']);
        Reminder::factory()->for(User::factory()->create())->create(['title' => 'Fremde']);

        $reminders = DataExport::for($user)['reminders'];

        $this->assertCount(1, $reminders);
        $this->assertSame('Meine', $reminders[0]['title']);
        $this->assertArrayNotHasKey('user_id', $reminders[0]);
    }
}
