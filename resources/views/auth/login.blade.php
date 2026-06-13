<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">Willkommen zurück</h1>
    <p class="mb-6 text-sm text-ink-faint">Melde dich an, um deine Listen zu sehen.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Passwort" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-ink-soft">
            <input id="remember_me" type="checkbox" class="rounded border-line bg-paper text-forest focus:ring-forest focus:ring-offset-0" name="remember">
            Angemeldet bleiben
        </label>

        <div class="flex items-center justify-between pt-1">
            @if (Route::has('password.request'))
                <a class="text-sm text-ink-faint transition hover:text-overprint" href="{{ route('password.request') }}">
                    Passwort vergessen?
                </a>
            @endif
            <x-primary-button>Anmelden</x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-ink-faint">
        Noch kein Konto?
        <a href="{{ route('register') }}" class="font-medium text-forest transition hover:text-overprint">Registrieren</a>
    </p>
</x-guest-layout>
