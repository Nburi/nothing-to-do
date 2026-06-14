<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">Passwort zurücksetzen</h1>
    <p class="mb-6 text-sm text-ink-soft">
        Kein Problem. Gib deine E-Mail an, und wir schicken dir einen Link, mit dem du ein neues Passwort wählen kannst.
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-1">
            <a href="{{ route('login') }}" class="text-sm text-ink-faint transition hover:text-overprint">Zurück zur Anmeldung</a>
            <x-primary-button>Link senden</x-primary-button>
        </div>
    </form>
</x-guest-layout>
