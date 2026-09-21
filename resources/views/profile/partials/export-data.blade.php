<section class="space-y-4">
    <header>
        <h2 class="text-base font-medium text-ink">Deine Daten</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Lade alles herunter, was du in nothing-to-do geschrieben hast — Aufgaben, Projekte, Gruppen,
            Agenda, Bastelideen, Zeitplan und Einstellungen — als eine Datei (JSON). Passwort und Zugangs-Tokens
            sind nie enthalten.
        </p>
    </header>

    {{-- A plain link: it works without JS and the browser shows its own download UI.
         Not wire:navigate — this is a file, not a page. --}}
    <a
        href="{{ route('profile.export') }}"
        download
        class="inline-flex items-center gap-2 rounded-card border border-line bg-paper px-4 py-2.5 text-sm font-medium text-ink transition hover:border-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-overprint"
    >
        <svg class="h-4 w-4 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3v10m0 0 4-4m-4 4-4-4M4 16h12"/></svg>
        Daten herunterladen
    </a>
</section>
