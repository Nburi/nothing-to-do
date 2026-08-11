<?php

use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Agenda;
use App\Livewire\CraftIdeas;
use App\Livewire\EmergencyMode;
use App\Livewire\GroupPage;
use App\Livewire\JoinAgendaSpace;
use App\Livewire\PrepareTomorrow;
use App\Livewire\ProjectPage;
use App\Livewire\Schedule;
use App\Livewire\Settings;
use App\Livewire\TaskBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('app')
        : view('welcome');
})->name('home');

Route::get('/app', TaskBoard::class)
    ->middleware('auth')
    ->name('app');

Route::get('/app/projects/{project}', ProjectPage::class)
    ->middleware('auth')
    ->name('project.show');

Route::get('/app/groups/{group}', GroupPage::class)
    ->middleware('auth')
    ->name('group.show');

Route::get('/app/emergency', EmergencyMode::class)
    ->middleware('auth')
    ->name('emergency');

Route::get('/app/schedule', Schedule::class)
    ->middleware('auth')
    ->name('schedule');

Route::get('/app/prepare', PrepareTomorrow::class)
    ->middleware('auth')
    ->name('prepare');

Route::get('/app/settings', Settings::class)
    ->middleware('auth')
    ->name('settings');

Route::get('/app/agenda', Agenda::class)
    ->middleware('auth')
    ->name('agenda');

// Invite link into a shared class agenda. Auth-gated: a guest is sent to login
// and lands back here afterwards (see RegisteredUserController — registration
// honours the intended URL too, since "classmate without an account clicks the
// link" is the normal way people join).
Route::get('/app/agenda/join/{code}', JoinAgendaSpace::class)
    ->middleware('auth')
    ->name('agenda.join');

Route::get('/app/crafts', CraftIdeas::class)
    ->middleware('auth')
    ->name('crafts');

// Presence heartbeat — see PresenceController and the heartbeat block in app.js.
Route::post('/app/heartbeat', PresenceController::class)
    ->middleware('auth')
    ->name('presence.heartbeat');

Route::view('/docs/api', 'docs.api', ['apiBase' => url('/api')])
    ->middleware('auth')
    ->name('docs.api');

// Breeze posts login/registration through to route('dashboard'); send it to the board.
Route::redirect('/dashboard', '/app')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
