<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">Passwort bestätigen</h1>
    <p class="mb-6 text-sm text-ink-soft">
        Dies ist ein geschützter Bereich. Bitte bestätige dein Passwort, um fortzufahren.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" value="Passwort" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-1">
            <x-primary-button>Bestätigen</x-primary-button>
        </div>
    </form>
</x-guest-layout>
