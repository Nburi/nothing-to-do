<?php

/*
 * German translation of Laravel's built-in auth messages — used directly by
 * Breeze's LoginRequest ('auth.failed', 'auth.throttle') and
 * ConfirmablePasswordController ('auth.password'). Same fix as
 * lang/de/validation.php: these were falling through to English with no
 * lang/ directory in the project at all.
 */

return [

    'failed' => 'Diese Zugangsdaten wurden nicht gefunden.',
    'password' => 'Das eingegebene Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuche es in :seconds Sekunden erneut.',

];
