<section class="space-y-5">
    <header>
        <h2 class="text-base font-medium text-ink">Konto löschen</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Beim Löschen werden alle Aufgaben und Daten unwiderruflich entfernt. Sichere vorher, was du behalten möchtest.
        </p>
    </header>

    <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        Konto löschen
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-base font-medium text-ink">Konto wirklich löschen?</h2>

            <p class="mt-2 text-sm text-ink-soft">
                Alle Aufgaben und Daten werden unwiderruflich gelöscht. Gib dein Passwort ein, um das Löschen zu bestätigen.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Passwort" class="sr-only" />
                <x-text-input id="password" name="password" type="password" class="w-3/4" placeholder="Passwort" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Abbrechen</x-secondary-button>
                <x-danger-button>Konto löschen</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
