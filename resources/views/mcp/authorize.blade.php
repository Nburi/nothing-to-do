{{--
    The OAuth consent screen (Passport::authorizationView in AppServiceProvider).
    Replaces Laravel MCP's published stock view, which is written against the
    shadcn-style tokens of Laravel's starter kits (bg-card, text-muted-foreground,
    bg-primary) — none of which exist in this app's Tailwind v3 Topografie theme,
    so it rendered as unstyled text. This is a page a real person sees at the
    exact moment they hand an AI access to their account; it has to look like the
    product, not like a framework default.

    Parameters come from Laravel\Passport\Http\Controllers\AuthorizationController:
    client, user, scopes, request, authToken. Only auth_token is actually needed
    by the approve/deny controllers (RetrievesAuthRequestFromSession) — the stock
    view's empty state/client_id hidden inputs are vestigial and left out here.
--}}
@php
    $permissions = [
        ['label' => 'Deine Aufgaben, Projekte, Agenda und Einstellungen lesen', 'granted' => true],
        ['label' => 'Aufgaben anlegen, ändern und abhaken', 'granted' => $user->mcpOAuthCan(\App\Mcp\McpAbility::WRITE)],
        ['label' => 'Aufgaben endgültig löschen', 'granted' => $user->mcpOAuthCan(\App\Mcp\McpAbility::DELETE)],
    ];

    $redirectHost = collect($client->redirect_uris ?? [])
        ->map(fn ($uri) => parse_url($uri, PHP_URL_HOST))
        ->filter()
        ->unique()
        ->implode(', ');
@endphp

<x-guest-layout>
    <h1 class="mb-1 text-lg font-medium text-ink">Zugriff erlauben?</h1>
    <p class="mb-5 text-sm text-ink-faint">
        <span class="font-medium text-ink-soft">{{ $client->name }}</span> möchte sich mit deinem
        nothing-to-do-Konto verbinden.
    </p>

    <div class="mb-5 rounded-card border border-line bg-paper px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-ink-faint">Angemeldet als</p>
        <p class="mt-0.5 text-sm font-medium text-ink">{{ $user->email }}</p>
    </div>

    <ul class="mb-5 space-y-2.5">
        @foreach ($permissions as $permission)
            <li class="flex items-start gap-2.5 text-sm {{ $permission['granted'] ? 'text-ink-soft' : 'text-ink-faint' }}">
                @if ($permission['granted'])
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 10.5 8 14.5 16 6" />
                    </svg>
                @else
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6l8 8M14 6l-8 8" />
                    </svg>
                @endif
                <span @class(['line-through decoration-line' => ! $permission['granted']])>{{ $permission['label'] }}</span>
            </li>
        @endforeach
    </ul>

    <p class="mb-5 text-xs leading-relaxed text-ink-faint">
        Du änderst diese Rechte jederzeit unter
        <a href="{{ route('settings') }}#developer" class="font-medium text-forest transition hover:text-overprint">Einstellungen → Shortcuts, API &amp; MCP</a> —
        auch nach dem Verbinden, ohne die Verbindung neu aufzubauen.
        @if ($redirectHost !== '')
            <br>Nach dem Bestätigen wirst du zu <span class="font-medium text-ink-soft">{{ $redirectHost }}</span> zurückgeleitet.
        @endif
    </p>

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="w-full rounded-lg border border-line bg-surface px-4 py-2 text-sm font-medium text-ink-soft transition hover:bg-paper hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Abbrechen
            </button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="w-full rounded-lg bg-forest px-4 py-2 text-sm font-medium text-white transition hover:bg-forest/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Verbinden
            </button>
        </form>
    </div>
</x-guest-layout>
