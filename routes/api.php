<?php

use App\Http\Controllers\Api\AgendaEntryController;
use App\Http\Controllers\Api\AgendaSpaceController;
use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\EventTemplateController;
use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ScheduleEventController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskGroupController;
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
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mcp', McpController::class);

    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/task-groups', [TaskGroupController::class, 'index']);
    Route::post('/task-groups', [TaskGroupController::class, 'store']);
    Route::get('/task-groups/{group}', [TaskGroupController::class, 'show']);
    Route::patch('/task-groups/{group}', [TaskGroupController::class, 'update']);
    Route::delete('/task-groups/{group}', [TaskGroupController::class, 'destroy']);

    Route::get('/agenda-entries', [AgendaEntryController::class, 'index']);
    Route::post('/agenda-entries', [AgendaEntryController::class, 'store']);
    Route::get('/agenda-entries/{entry}', [AgendaEntryController::class, 'show']);
    Route::patch('/agenda-entries/{entry}', [AgendaEntryController::class, 'update']);
    Route::delete('/agenda-entries/{entry}', [AgendaEntryController::class, 'destroy']);
    Route::get('/agenda-spaces', [AgendaSpaceController::class, 'index']);

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

    Route::get('/event-categories', [EventCategoryController::class, 'index']);
    Route::post('/event-categories', [EventCategoryController::class, 'store']);
    Route::patch('/event-categories/{category}', [EventCategoryController::class, 'update']);
    Route::delete('/event-categories/{category}', [EventCategoryController::class, 'destroy']);
    Route::put('/event-categories/{category}/task-link', [EventCategoryController::class, 'setTaskLink']);
    Route::delete('/event-categories/{category}/task-link', [EventCategoryController::class, 'clearTaskLink']);

    Route::get('/event-templates', [EventTemplateController::class, 'index']);
    Route::post('/event-templates/{template}/apply', [EventTemplateController::class, 'apply']);
    Route::delete('/event-templates/{template}', [EventTemplateController::class, 'destroy']);
});
