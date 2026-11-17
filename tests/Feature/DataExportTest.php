<?php

namespace Tests\Feature;

use App\Models\CraftIdea;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_anything(): void
    {
        $this->get(route('profile.export'))->assertRedirect(route('login'));
    }

    public function test_the_download_is_a_json_attachment_that_is_never_cached(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.export'));

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=nothing-to-do-export-'.now()->format('Y-m-d').'.json', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $this->assertSame('nothing-to-do', json_decode($response->streamedContent(), true)['meta']['app'] ?? null);
    }

    /** Inserts a row into a table whose model was removed with its feature. */
    private function legacyRow(string $table, User $user, array $values): int
    {
        return DB::table($table)->insertGetId([...$values, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_it_contains_the_users_own_data_across_sections(): void
    {
        $user = User::factory()->create(['name' => 'Lena', 'email' => 'lena@example.test']);
        Task::factory()->for($user)->inbox()->create(['title' => 'Steuern', 'notes' => 'bis Freitag']);
        Task::factory()->for($user)->completed()->create(['title' => 'Alt und erledigt']);
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus']);
        EventCategory::factory()->for($user)->create(['name' => 'Training']);
        // Projekte, Gruppen and the Agenda are gone from the app, but their rows stay
        // in the database — the export still has to hand them back.
        $this->legacyRow('projects', $user, ['name' => 'Maturarbeit']);
        $groupId = $this->legacyRow('task_groups', $user, ['name' => 'Zimmer']);
        DB::table('group_notes')->insert(['task_group_id' => $groupId, 'content' => 'Kartons besorgen', 'created_at' => now(), 'updated_at' => now()]);
        $entryId = $this->legacyRow('agenda_entries', $user, ['type' => 'homework', 'subject' => 'Deutsch', 'title' => 'Aufsatz', 'date' => '2026-09-24']);
        DB::table('agenda_entry_notes')->insert(['agenda_entry_id' => $entryId, 'user_id' => $user->id, 'notes' => 'nur für mich', 'created_at' => now(), 'updated_at' => now()]);

        $data = DataExport::for($user);

        $this->assertSame('Lena', $data['account']['name']);
        $this->assertSame(DataExport::FORMAT_VERSION, $data['meta']['format_version']);
        $this->assertEqualsCanonicalizing(['Steuern', 'Alt und erledigt'], collect($data['tasks'])->pluck('title')->all());
        $this->assertSame('bis Freitag', collect($data['tasks'])->firstWhere('title', 'Steuern')['notes']);
        $this->assertTrue(collect($data['tasks'])->firstWhere('title', 'Alt und erledigt')['is_completed']);
        $this->assertSame(['Maturarbeit'], collect($data['projects'])->pluck('name')->all());
        $this->assertSame(['Zimmer'], collect($data['task_groups'])->pluck('name')->all());
        $this->assertSame(['Kartons besorgen'], $data['task_groups'][0]['notes']);
        $this->assertSame(['Vogelhaus'], collect($data['craft_ideas'])->pluck('title')->all());
        $this->assertSame(['Training'], collect($data['event_categories'])->pluck('name')->all());
        $this->assertSame('nur für mich', $data['agenda_entries'][0]['private_note']);
        $this->assertFalse($data['agenda_entries'][0]['done']);
        $this->assertFalse($data['agenda_entries'][0]['shared_with_class']);
    }

    public function test_it_never_contains_another_users_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'other@example.test']);
        Task::factory()->for($other)->create(['title' => 'Fremdes Geheimnis']);
        $this->legacyRow('projects', $other, ['name' => 'Fremdes Projekt']);
        $this->legacyRow('agenda_entries', $other, ['type' => 'homework', 'subject' => 'Mathe', 'title' => 'Fremde Aufgabe', 'date' => '2026-09-24']);

        $json = json_encode(DataExport::for($user));

        $this->assertStringNotContainsString('Fremdes Geheimnis', $json);
        $this->assertStringNotContainsString('Fremdes Projekt', $json);
        $this->assertStringNotContainsString('Fremde Aufgabe', $json);
        $this->assertStringNotContainsString('other@example.test', $json);
    }

    public function test_it_never_leaks_credentials_or_push_keys(): void
    {
        $user = User::factory()->create(['remember_token' => 'geheimes-remember-token']);
        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.test/abc',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/abc'),
            'p256dh' => 'p256dh-key-material',
            'auth_token' => 'auth-secret-material',
        ]);
        $user->createToken('shortcut');

        $json = json_encode(DataExport::for($user));

        $this->assertStringNotContainsString($user->password, $json);
        $this->assertStringNotContainsString('geheimes-remember-token', $json);
        $this->assertStringNotContainsString('push.example.test', $json);
        $this->assertStringNotContainsString('p256dh-key-material', $json);
        $this->assertStringNotContainsString('auth-secret-material', $json);
        $this->assertStringNotContainsString('shortcut', $json);
    }

    public function test_umlauts_and_slashes_stay_readable_in_the_file(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->inbox()->create(['title' => 'Käse & Brot / Milch']);

        $body = $this->actingAs($user)->get(route('profile.export'))->streamedContent();

        $this->assertStringContainsString('Käse & Brot / Milch', $body);
        $this->assertNotNull(json_decode($body, true));
    }

    public function test_the_route_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->get(route('profile.export'))->assertOk();
        }

        $this->actingAs($user)->get(route('profile.export'))->assertStatus(429);
    }

    public function test_the_profile_page_offers_the_download(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('profile.export'), false)
            ->assertSee('Daten herunterladen');
    }
}
