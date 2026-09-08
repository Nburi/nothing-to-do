{{--
    Dedicated maintenance page — deliberately NOT @include('errors.5xx'), unlike every other
    bundled status code (401/402/403/419/429/500). A 503 in this app is (near-)exclusively
    php artisan down's own PreventRequestsDuringMaintenance middleware, not a genuine failure —
    it deserves calmer, specific copy ("we'll be back shortly") rather than the generic "etwas
    ist schiefgelaufen" every other 5xx gets. See CLAUDE.md, "Fehler-Statistiken".

    Laravel's maintenance middleware always throws HttpException(503, 'Service Unavailable', ...)
    — that literal string is hardcoded in the framework, not the admin's own --message text (that
    flag isn't even wired into the exception) — so $exception->getMessage() has nothing worth
    surfacing here; this page's own copy is static.
--}}
@include('errors.shell', [
    'title' => 'Wartungsarbeiten',
    'heading' => 'Wir sind gleich wieder da.',
    'message' => 'nothing-to-do wird gerade kurz gewartet. Das dauert normalerweise nur wenige Minuten — versuch es gleich nochmal.',
    'icon' => 'maintenance-icon',
    'reload' => true,
])
