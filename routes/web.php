<?php

use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Admin\AnnouncementEditor;
use App\Livewire\Admin\ArticleEditor;
use App\Livewire\Agenda;
use App\Livewire\ArticleShow;
use App\Livewire\CraftIdeas;
use App\Livewire\EmergencyMode;
use App\Livewire\GroupPage;
use App\Livewire\JoinAgendaSpace;
use App\Livewire\Library;
use App\Livewire\Onboarding;
use App\Livewire\Planner;
use App\Livewire\PrepareTomorrow;
use App\Livewire\Progress;
use App\Livewire\ProjectPage;
use App\Livewire\Schedule;
use App\Livewire\Settings;
use App\Livewire\TaskBoard;
use App\Livewire\WeekPlan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->defaultLandingRouteName())
        : view('welcome');
})->name('home');

// Only "/" is public (everything else sits behind auth), so both files are
// generated rather than static — that keeps them correct for whatever
// APP_URL the app is actually deployed under, with no hardcoded domain.
Route::get('/robots.txt', function () {
    return response(
        "User-agent: *\nDisallow: /app\nDisallow: /profile\nDisallow: /docs\n\nSitemap: ".url('/sitemap.xml')."\n"
    )->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $sitemapUrl = url('/');
    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url>
            <loc>{$sitemapUrl}</loc>
            <changefreq>monthly</changefreq>
            <priority>1.0</priority>
        </url>
    </urlset>
    XML;

    return response($xml)->header('Content-Type', 'application/xml');
})->name('sitemap');

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

Route::get('/app/weekplan', WeekPlan::class)
    ->middleware('auth')
    ->name('weekplan');

Route::get('/app/planner', Planner::class)
    ->middleware('auth')
    ->name('planner');

Route::get('/app/prepare', PrepareTomorrow::class)
    ->middleware('auth')
    ->name('prepare');

Route::get('/app/settings', Settings::class)
    ->middleware('auth')
    ->name('settings');

Route::get('/app/onboarding', Onboarding::class)
    ->middleware('auth')
    ->name('onboarding');

// Admin-only — gated in AnnouncementEditor::mount(), not middleware (see its
// docblock for why this app keeps that boundary at the component level).
Route::get('/app/admin/announcements', AnnouncementEditor::class)
    ->middleware('auth')
    ->name('admin.announcements');

// Admin-only — gated in ArticleEditor::mount(), same convention as above.
Route::get('/app/admin/library', ArticleEditor::class)
    ->middleware('auth')
    ->name('admin.library');

Route::get('/app/library', Library::class)
    ->middleware('auth')
    ->name('library');

Route::get('/app/library/{article}', ArticleShow::class)
    ->middleware('auth')
    ->name('library.show');

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

Route::get('/app/progress', Progress::class)
    ->middleware('auth')
    ->name('progress');

// Presence heartbeat — see PresenceController and the heartbeat block in app.js.
Route::post('/app/heartbeat', PresenceController::class)
    ->middleware('auth')
    ->name('presence.heartbeat');

Route::view('/docs/api', 'docs.api', ['apiBase' => url('/api')])
    ->middleware('auth')
    ->name('docs.api');

// Breeze posts login/registration through to route('dashboard'); send it to
// whichever page the user has chosen as their default landing page (see
// User::defaultLandingRouteName() / App\Services\AppModules), falling back to
// the board for a guest hitting this URL directly (matches the previous
// static redirect's behavior — /app itself bounces a guest on to login).
Route::get('/dashboard', function () {
    return redirect()->route(auth()->check() ? auth()->user()->defaultLandingRouteName() : 'app');
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
