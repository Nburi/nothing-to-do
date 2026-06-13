<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-5 px-5 py-10 sm:px-6">
        <div class="flex items-center gap-3">
            <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-xl font-medium text-ink">Profil</h1>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 shadow-map sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
