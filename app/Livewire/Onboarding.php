<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesModuleSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The new-user tutorial: one continuous, skippable walkthrough covering the
 * "3 Things" framework (see CLAUDE.md §1) and every feature area of the app,
 * ending on an interactive step for module visibility + default landing page
 * (App\Services\AppModules — the two settings a classmate who only cares
 * about the shared class Agenda would actually want to touch on day one).
 *
 * Step progression itself is pure client-side state (the `onboarding` Alpine
 * store in app.js) — the slides are static content, not data, so there's
 * nothing here for the server to track between them. The only server round
 * trips are the module-visibility step's real toggles (via
 * ManagesModuleSettings, shared verbatim with Settings) and finishing/
 * skipping, both of which just stamp `onboarding_completed_at` and send the
 * user on to wherever they've chosen to land.
 *
 * Reachable two ways: automatically, once, right after a brand-new
 * registration (RegisteredUserController, gated on
 * User::needsOnboarding()); and any time after that from Settings' "Tutorial"
 * card, for someone who finished it, skipped it, or never saw it at all —
 * visiting this route never itself flips anything, only finish()/skip() do.
 */
#[Layout('layouts.app')]
class Onboarding extends Component
{
    use ManagesModuleSettings;

    /** Skipping counts as "seen it", exactly like finishing — see User::markOnboardingSeen(). */
    public function skip(): void
    {
        auth()->user()->markOnboardingSeen();

        $this->redirectRoute(auth()->user()->defaultLandingRouteName(), navigate: true);
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
