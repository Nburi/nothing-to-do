<?php

namespace Tests\Feature;

use App\Livewire\Onboarding;
use App\Livewire\Settings;
use App\Models\User;
use App\Services\AppModules;
use App\Services\OnboardingQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tutorial_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))
            ->assertOk()
            ->assertSee('Willkommen bei nothing-to-do')
            ->assertSee('Warum suchst du gerade eine To-Do-Liste?')
            ->assertSee('Die 3 Dinge');
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
    }

    public function test_the_konzept_vertiefung_step_links_to_the_list_concept_setting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))
            ->assertOk()
            ->assertSee(route('settings').'?highlight='.urlencode('#list-concept'), false);
    }

    public function test_a_brand_new_account_needs_onboarding(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->needsOnboarding());
    }

    public function test_finishing_stamps_onboarding_completed_and_redirects_to_the_default_page(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('finish')
            ->assertRedirect(route('agenda'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->needsOnboarding());
        $this->assertNotNull($fresh->onboarding_completed_at);
    }

    public function test_skipping_stamps_onboarding_completed_without_redirecting(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('skip')
            ->assertNoRedirect();

        $this->assertFalse($user->fresh()->needsOnboarding());
    }

    public function test_a_user_who_already_finished_can_replay_it_and_the_timestamp_moves_forward(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()->subDays(10)]);
        $firstSeenAt = $user->onboarding_completed_at;

        Livewire::actingAs($user)->test(Onboarding::class)->call('finish');

        $this->assertTrue($user->fresh()->onboarding_completed_at->gt($firstSeenAt));
    }

    public function test_toggling_a_module_from_inside_the_tutorial_persists_like_settings(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)->call('toggleModule', 'agenda');

        $this->assertSame(['agenda'], $user->fresh()->hidden_modules);
    }

    public function test_setting_the_default_page_from_inside_the_tutorial_persists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)->call('setDefaultPage', 'schedule');

        $this->assertSame('schedule', $user->fresh()->default_page);
    }

    public function test_settings_always_offers_the_tutorial_link_regardless_of_completion_state(): void
    {
        $neverSeen = User::factory()->create();
        $alreadyDone = User::factory()->create(['onboarding_completed_at' => now()]);

        Livewire::actingAs($neverSeen)->test(Settings::class)
            ->assertSee(route('onboarding'))
            ->assertSee('Tutorial starten');

        Livewire::actingAs($alreadyDone)->test(Settings::class)
            ->assertSee(route('onboarding'))
            ->assertSee('Nochmal ansehen');
    }

    // ── Quiz resolution (App\Services\OnboardingQuiz) ──────────────────

    public function test_resolve_falls_back_to_simple_when_no_answer_carries_a_concept_vote(): void
    {
        $result = OnboardingQuiz::resolve(['bored', 'just_browsing']);

        $this->assertSame('simple', $result['concept']);
        $this->assertSame(['crafts'], $result['modules']);
    }

    public function test_resolve_falls_back_to_simple_when_nothing_is_selected_at_all(): void
    {
        $result = OnboardingQuiz::resolve([]);

        $this->assertSame('simple', $result['concept']);
        $this->assertSame([], $result['modules']);
    }

    public function test_resolve_tallies_votes_and_the_concept_with_the_most_wins(): void
    {
        // "plan_the_day" and "in_progress_done" both vote kanban; "mixed_sizes" votes three_things once.
        $result = OnboardingQuiz::resolve(['plan_the_day', 'in_progress_done', 'mixed_sizes']);

        $this->assertSame('kanban', $result['concept']);
        $this->assertEqualsCanonicalizing(['schedule', 'weekplan', 'prepare', 'progress'], $result['modules']);
    }

    public function test_resolve_breaks_a_tie_in_favor_of_whichever_concept_appears_first_in_the_catalog(): void
    {
        // "simple_list" (simple) and "important_urgent" (eisenhower) each cast exactly one vote;
        // simple_list is earlier in OnboardingQuiz::ANSWERS, so simple wins the tie.
        $result = OnboardingQuiz::resolve(['simple_list', 'important_urgent']);

        $this->assertSame('simple', $result['concept']);
    }

    public function test_resolve_unions_modules_across_every_checked_answer_without_duplicates(): void
    {
        // "shared_homework" and "plan_the_day" both vote for a concept; only modules matter here.
        $result = OnboardingQuiz::resolve(['shared_homework', 'mixed_sizes', 'stay_consistent']);

        $this->assertEqualsCanonicalizing(['agenda', 'progress'], $result['modules']);
    }

    // ── Applying the quiz (App\Livewire\Onboarding::applyQuizAnswers) ──

    public function test_applying_quiz_answers_persists_the_resolved_concept(): void
    {
        $user = User::factory()->create(['list_concept' => 'three_things']);

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('applyQuizAnswers', ['in_progress_done']);

        $this->assertSame('kanban', $user->fresh()->list_concept);
    }

    public function test_applying_quiz_answers_hides_every_module_not_in_the_resolved_set(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('applyQuizAnswers', ['important_urgent']); // eisenhower + emergency only

        $expectedHidden = collect(AppModules::CATALOG)->keys()->reject(fn ($k) => $k === 'emergency')->values()->all();

        $this->assertEqualsCanonicalizing($expectedHidden, $user->fresh()->hidden_modules);
    }

    public function test_applying_quiz_answers_with_no_module_votes_hides_every_module(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('applyQuizAnswers', ['just_browsing']);

        $this->assertEqualsCanonicalizing(array_keys(AppModules::CATALOG), $user->fresh()->hidden_modules);
    }

    public function test_hidden_modules_get_no_feature_step_while_visible_ones_still_do(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda', 'crafts']]);

        $html = Livewire::actingAs($user)->test(Onboarding::class)->html();

        // Every module's description appears once, in its Feature-Galerie toggle row.
        // A currently-visible module's description appears a *second* time, in its own
        // dedicated feature step below — a hidden module never gets that second slide.
        // e() mirrors Blade's own {{ }} escaping (some descriptions contain a literal
        // `"`, which Blade renders as `&quot;`).
        $this->assertSame(1, substr_count($html, e(AppModules::CATALOG['agenda']['description'])));
        $this->assertSame(1, substr_count($html, e(AppModules::CATALOG['crafts']['description'])));
        $this->assertSame(2, substr_count($html, e(AppModules::CATALOG['schedule']['description'])));
        $this->assertSame(2, substr_count($html, e(AppModules::CATALOG['emergency']['description'])));
    }
}
