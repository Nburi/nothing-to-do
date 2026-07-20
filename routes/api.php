<?php

use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\EventTemplateController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ScheduleEventController;
use App\Http\Controllers\Api\TaskController;
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
*/

Route::middleware('auth:sanctum')->group(function () {
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

    Route::get('/event-templates', [EventTemplateController::class, 'index']);
    Route::post('/event-templates/{template}/apply', [EventTemplateController::class, 'apply']);
    Route::delete('/event-templates/{template}', [EventTemplateController::class, 'destroy']);
});
