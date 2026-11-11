<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\ArrivalController;
use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\EventTemplateController;
use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\ScheduleEventController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Middleware\RejectNarrowTokens;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Token-authenticated JSON API for third-party integrations (Apple
| Shortcuts, etc.). Every route below requires a Sanctum personal access
| token (Settings → Shortcuts & API) and is scoped to that token's user —
| see the in-app API docs page (/docs/api) for the full reference.
|
| /mcp is a separate concern living in the same auth:sanctum group: a
| single JSON-RPC (Model Context Protocol) endpoint for AI assistants —
| see App\Http\Controllers\Api\McpController and /docs/mcp.
|
| Single-purpose phone tokens (App\Support\NarrowAbility) only reach their
| own route; RejectNarrowTokens keeps them out of everything else.
|
*/

// Refactor spike 0.1: the "home Wi-Fi" Shortcut reports an arrival and gets a Web Push.
Route::middleware(['auth:sanctum', 'throttle:6,1'])->post('/arrival', ArrivalController::class);

// Refactor step 1.5: one line into the Inbox, from the capture Shortcut (narrow token) or any full token.
Route::middleware(['auth:sanctum', 'throttle:30,1'])->post('/capture', CaptureController::class);

Route::middleware(['auth:sanctum', RejectNarrowTokens::class])->group(function () {
    Route::post('/mcp', McpController::class);

    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/schedule-events/focus', [ScheduleEventController::class, 'focus']);
    Route::get('/schedule-events', [ScheduleEventController::class, 'index']);
    Route::post('/schedule-events', [ScheduleEventController::class, 'store']);
    Route::get('/schedule-events/{event}', [ScheduleEventController::class, 'show']);
    Route::patch('/schedule-events/{event}', [ScheduleEventController::class, 'update']);
    Route::delete('/schedule-events/{event}', [ScheduleEventController::class, 'destroy']);
    Route::post('/schedule-events/{event}/start-focus', [ScheduleEventController::class, 'startFocus']);
    Route::post('/schedule-events/{event}/stop-focus', [ScheduleEventController::class, 'stopFocus']);
    Route::post('/schedule-events/{event}/continue-focus', [ScheduleEventController::class, 'continueFocus']);
    Route::post('/schedule-events/{event}/skip-focus-break', [ScheduleEventController::class, 'skipFocusBreak']);

    // Refactor step 1.5: the planning engines (suggestion, battery, session, forecast, crunch decisions) and areas.
    Route::get('/suggestion', [PlanningController::class, 'suggestion']);
    Route::post('/suggestion/another', [PlanningController::class, 'another']);
    Route::put('/evening/battery', [PlanningController::class, 'battery']);
    Route::get('/session', [PlanningController::class, 'session']);
    Route::post('/session/{action}', [PlanningController::class, 'sessionAction'])->whereIn('action', ['start', 'done', 'pause', 'resume', 'stop']);
    Route::get('/forecast', [PlanningController::class, 'forecast']);
    Route::post('/tasks/{task}/drop', [PlanningController::class, 'drop']);
    Route::post('/tasks/{task}/shrink', [PlanningController::class, 'shrink']);

    Route::get('/areas', [AreaController::class, 'index']);
    Route::post('/areas', [AreaController::class, 'store']);
    Route::patch('/areas/{area}', [AreaController::class, 'update']);
    Route::delete('/areas/{area}', [AreaController::class, 'destroy']);

    Route::get('/event-categories', [EventCategoryController::class, 'index']);
    Route::post('/event-categories', [EventCategoryController::class, 'store']);
    Route::patch('/event-categories/{category}', [EventCategoryController::class, 'update']);
    Route::delete('/event-categories/{category}', [EventCategoryController::class, 'destroy']);

    Route::get('/event-templates', [EventTemplateController::class, 'index']);
    Route::post('/event-templates/{template}/apply', [EventTemplateController::class, 'apply']);
    Route::delete('/event-templates/{template}', [EventTemplateController::class, 'destroy']);
});
