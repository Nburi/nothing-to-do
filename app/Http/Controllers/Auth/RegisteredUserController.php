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

        $attributes = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ];

        // Best-effort default from the browser's own clock (see resources/js/app.js's
        // detectTimezoneDefaults(), called via a hidden field in auth/register.blade.php)
        // — ignored rather than validated-and-rejected when missing or out of range (JS
        // disabled, a non-browser signup), so registration itself never depends on it.
        $timezoneOffset = $request->input('timezone_offset');
        if (is_numeric($timezoneOffset) && $timezoneOffset >= -12 && $timezoneOffset <= 14) {
            $attributes['timezone_offset'] = (float) $timezoneOffset;
            $attributes['timezone_auto_dst'] = $request->boolean('timezone_auto_dst');
        }

        $user = User::create($attributes);

        event(new Registered($user));

        Auth::login($user);

        // intended(), not a bare redirect: a pending destination (e.g. a link
        // followed before registering) wins over the onboarding tutorial; only
        // when there's none does a brand-new account fall through to onboarding
        // instead of straight to the board.
        $default = $user->needsOnboarding()
            ? route('onboarding', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($default);
    }
}
