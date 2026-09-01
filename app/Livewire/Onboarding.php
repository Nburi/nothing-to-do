<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesListConceptSettings;
use App\Livewire\Concerns\ManagesModuleSettings;
use App\Services\AppModules;
use App\Services\OnboardingQuiz;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The new-user tutorial: one continuous, skippable walkthrough that opens
 * with a one-question quiz ("Warum suchst du gerade eine To-Do-Liste?",
 * App\Services\OnboardingQuiz) and uses it to pre-select a board mental
 * model (App\Services\ListConcepts) and a set of optional feature areas
 * (App\Services\AppModules) — both real, immediate-save settings, freely
 * overridable right there in the tutorial. Every activated feature area then
 * gets exactly one explanation step of its own; the step count is therefore
 * not fixed, it's derived live from how many areas are currently on (see
 * activeFeatureSteps()).
 *
 * Step *position* stays pure client-side state (the `onboarding` Alpine
 * store in app.js) — even the dynamic step count is just read out of the
 * Blade view on every render, the same way the old fixed count was. The
 * server only ever does real work for: applying the quiz result
 * (applyQuizAnswers), the Konzept/Feature-Galerie steps' real toggles (via
 * ManagesListConceptSettings/ManagesModuleSettings, shared verbatim with
 * Settings), and finishing/skipping, which stamp `onboarding_completed_at`.
 *
 * Reachable two ways: automatically, once, right after a brand-new
 * registration (RegisteredUserController, gated on
 * User::needsOnboarding()); and any time after that from Settings' "Tutorial"
 * card, for someone who finished it, skipped it, or never saw it at all —
 * visiting this route never itself flips anything, only finish()/apply/
 * skip() do.
 */
#[Layout('layouts.app')]
class Onboarding extends Component
{
    use ManagesModuleSettings;
    use ManagesListConceptSettings;

    /**
     * @return list<array{key: string, label: string}>
     */
    #[Computed]
    public function quizAnswers(): array
    {
        return collect(OnboardingQuiz::ANSWERS)
            ->map(fn (array $answer) => ['key' => $answer['key'], 'label' => $answer['label']])
            ->all();
    }

    /**
     * The AppModules::CATALOG keys, in the catalog's fixed order, currently
     * visible for this user — drives both the dynamic step count and which
     * per-feature slide renders. Starts out as "every module" for an account
     * that has never touched module visibility (nothing hidden yet), and
     * narrows the moment applyQuizAnswers() runs.
     *
     * @return list<string>
     */
    #[Computed]
    public function activeFeatureSteps(): array
    {
        $user = auth()->user();

        return collect(AppModules::CATALOG)->keys()
            ->filter(fn (string $key) => AppModules::isVisible($user, $key))
            ->values()
            ->all();
    }

    /**
     * Applies the quiz's resolved concept + module pre-selection — called
     * once, from the Frage step's own "Weiter" button, right before it
     * advances past that step. Both writes are real, immediate-save settings
     * (identical to a manual pick in Settings), not onboarding-local state:
     * the Konzept and Feature-Galerie steps right after this one are just
     * Settings' own pickers, freely overridable, so there is nothing left to
     * "confirm" later. See OnboardingQuiz::resolve() for the tally/tie-break
     * rules.
     *
     * @param  list<string>  $selectedKeys
     */
    public function applyQuizAnswers(array $selectedKeys): void
    {
        $result = OnboardingQuiz::resolve($selectedKeys);

        $this->setListConcept($result['concept']);

        $hiddenModules = collect(AppModules::CATALOG)->keys()
            ->reject(fn (string $key) => in_array($key, $result['modules'], true))
            ->values()
            ->all();

        auth()->user()->update(['hidden_modules' => $hiddenModules]);

        unset($this->moduleRows, $this->landingPageOptions, $this->activeFeatureSteps);
    }

    /**
     * Skipping counts as "seen it", exactly like finishing — see
     * User::markOnboardingSeen(). Deliberately does *not* redirect: the
     * client shows an inline "Übersprungen" confirmation instead (see the
     * `skipped` Alpine flag in the view), so leaving is a deliberate next
     * click rather than the page being yanked away mid-click.
     */
    public function skip(): void
    {
        auth()->user()->markOnboardingSeen();
    }

    public function finish(): void
    {
        auth()->user()->markOnboardingSeen();

        $this->redirectRoute(auth()->user()->defaultLandingRouteName(), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding');
    }
}
