<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        // Read the *previous* login before this one overwrites it — that's
        // the only value that can answer "how long was the gap". Flashing
        // the already-chosen message (not just a flag) into the session
        // means App\Livewire\FeatureAnnouncementToast never has to re-derive
        // "how long was the user away" itself; it just displays what's here.
        $previousLoginAt = $user->last_login_at;

        $user->update(['last_login_at' => now()]);

        if (
            $previousLoginAt !== null
            && (int) $previousLoginAt->diffInDays(now()) >= User::WELCOME_BACK_AWAY_DAYS
        ) {
            $request->session()->put('welcome_back_message', User::randomWelcomeBackMessage());
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
