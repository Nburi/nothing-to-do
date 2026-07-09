<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Token-authenticated JSON API for third-party integrations (Apple
| Shortcuts, etc.). Every route below requires a Sanctum personal access
| token (Settings → Shortcuts & API) and is scoped to that token's user —
| see docs/API.md / the in-app API docs page for the full reference.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    //
});
