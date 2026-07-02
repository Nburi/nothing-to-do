<?php

use App\Http\Controllers\ProfileController;
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

Route::get('/app/schedule', Schedule::class)
    ->middleware('auth')
    ->name('schedule');

Route::get('/app/settings', Settings::class)
    ->middleware('auth')
    ->name('settings');

// Breeze posts login/registration through to route('dashboard'); send it to the board.
Route::redirect('/dashboard', '/app')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
