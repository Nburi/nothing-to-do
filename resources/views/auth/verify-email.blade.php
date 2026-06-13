<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">E-Mail bestätigen</h1>
    <p class="mb-4 text-sm text-ink-soft">
        Danke für die Registrierung. Bitte bestätige deine E-Mail über den Link, den wir dir geschickt haben. Keine Mail erhalten? Wir senden gern eine neue.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-card bg-forest-soft px-3 py-2 text-sm font-medium text-forest">
            Ein neuer Bestätigungslink wurde an deine E-Mail gesendet.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Link erneut senden</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-ink-faint transition hover:text-overprint">Abmelden</button>
        </form>
    </div>
</x-guest-layout>
