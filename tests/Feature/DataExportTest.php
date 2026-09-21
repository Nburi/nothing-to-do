<?php

namespace Tests\Feature;

use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_contains_the_users_own_data_across_sections(): void
    {
        $user = User::factory()->create(['name' => 'Lena', 'email' => 'lena@example.test']);
        Task::factory()->for($user)->inbox()->create(['title' => 'Steuern', 'notes' => 'bis Freitag']);
        Task::factory()->for($user)->completed()->create(['title' => 'Alt und erledigt']);
        Project::factory()->for($user)->create(['name' => 'Maturarbeit']);
        TaskGroup::factory()->for($user)->create(['name' => 'Zimmer']);
        CraftIdea::factory()->for($user)->create(['title' => 'Vogelhaus']);
        EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $entry = AgendaEntry::factory()->for($user)->homework()->create(['title' => 'Aufsatz', 'subject' => 'Deutsch']);
        $entry->setPrivateNoteFor($user, 'nur für mich');

        $data = DataExport::for($user);

        $this->assertSame('Lena', $data['account']['name']);
        $this->assertSame(DataExport::FORMAT_VERSION, $data['meta']['format_version']);
        $this->assertEqualsCanonicalizing(['Steuern', 'Alt und erledigt'], collect($data['tasks'])->pluck('title')->all());
        $this->assertSame('bis Freitag', collect($data['tasks'])->firstWhere('title', 'Steuern')['notes']);
        $this->assertTrue(collect($data['tasks'])->firstWhere('title', 'Alt und erledigt')['is_completed']);
        $this->assertSame(['Maturarbeit'], collect($data['projects'])->pluck('name')->all());
        $this->assertSame(['Zimmer'], collect($data['task_groups'])->pluck('name')->all());
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
        Project::factory()->for($other)->create(['name' => 'Fremdes Projekt']);
        AgendaEntry::factory()->for($other)->homework()->create(['title' => 'Fremde Aufgabe']);

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
