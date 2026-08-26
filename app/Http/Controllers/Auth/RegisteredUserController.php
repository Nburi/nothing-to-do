<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // intended(), not a bare redirect: someone who followed a class-agenda
        // invite link has no account yet, so registration — not login — is the
        // step that has to hand them back to where they were going. Only when
        // there's no such destination pending does a brand-new account fall
        // through to the onboarding tutorial instead of straight to the board —
        // a classmate arriving via an invite link gets what they clicked, not a
        // detour through a walkthrough first.
        $default = $user->needsOnboarding()
            ? route('onboarding', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($default);
    }
}
