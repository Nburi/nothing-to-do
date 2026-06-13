<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">Konto erstellen</h1>
    <p class="mb-6 text-sm text-ink-faint">Drei Listen, ein klarer Tag. Leg los.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" class="mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Passwort" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Passwort bestätigen" />
            <x-text-input id="password_confirmation" class="mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="pt-1">
            <x-primary-button class="w-full">Registrieren</x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-ink-faint">
        Schon registriert?
        <a href="{{ route('login') }}" class="font-medium text-forest transition hover:text-overprint">Anmelden</a>
    </p>
</x-guest-layout>
