@php
    $copy = match ($exception?->getStatusCode() ?? 400) {
        401, 403 => [
            'title' => 'Kein Zugriff',
            'heading' => 'Dafür fehlt dir der Zugriff.',
            'message' => 'Entweder bist du nicht (mehr) angemeldet, oder diese Seite ist nicht für deinen Account gedacht.',
        ],
        419 => [
            'title' => 'Sitzung abgelaufen',
            'heading' => 'Deine Sitzung ist abgelaufen.',
            'message' => 'Das passiert, wenn eine Seite sehr lange offen war. Bitte lade die Seite neu und versuch es erneut.',
        ],
        429 => [
            'title' => 'Zu viele Anfragen',
            'heading' => 'Kurz durchatmen.',
            'message' => 'Da kam gerade zu viel auf einmal. Versuch es in ein paar Sekunden nochmal.',
        ],
        default => [
            'title' => 'Das hat nicht geklappt',
            'heading' => 'Das hat nicht geklappt.',
            'message' => 'Diese Anfrage konnte nicht bearbeitet werden.',
        ],
    };
@endphp
@include('errors.shell', $copy)
