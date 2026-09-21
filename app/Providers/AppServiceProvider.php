<?php

namespace App\Providers;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\TaskGroup;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WebPush::class, fn () => new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every id in a URL is a database id. Without this, "/api/tasks/abc" reached
        // controllers whose action takes `int $id` and died with a TypeError - a 500
        // (and an error-level log line) for what is simply "no such task". A
        // non-numeric segment now fails route matching itself: a plain 404.
        foreach (['task', 'project', 'group', 'entry', 'event', 'category', 'template'] as $param) {
            Route::pattern($param, '[0-9]+');
        }

        // Production sits behind a reverse proxy that terminates TLS — `trustProxies(at: '*')`
        // in bootstrap/app.php already makes Laravel trust its X-Forwarded-Proto header, so
        // url()/route() generation *should* already come out https without this. Forcing the
        // scheme here is the belt-and-suspenders guarantee: every generated URL (the MCP docs
        // page's endpoint URL included — see resources/views/docs/mcp.blade.php) is https
        // regardless of whether the proxy header ever gets misconfigured or dropped, which is
        // exactly the kind of thing that's invisible from inside the app until a client refuses
        // an http:// MCP endpoint outright. Gated on the environment so local http dev is untouched.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // A category's task link points at a project/group/Agenda entry. The FK itself is
        // nullOnDelete, but that only nulls the FK — `task_source` would keep saying "project"
        // with nothing behind it, so the link sheet showed an active chip over an empty picker.
        // Reset `task_source` here, while the FK still identifies the affected categories (the
        // DB nulls it during the delete itself, i.e. after `deleting` but before `deleted`).
        Project::deleting(fn (Project $project) => $this->clearCategoryLinks('linked_project_id', $project->id));
        TaskGroup::deleting(fn (TaskGroup $group) => $this->clearCategoryLinks('linked_group_id', $group->id));
        AgendaEntry::deleting(fn (AgendaEntry $entry) => $this->clearCategoryLinks('linked_agenda_entry_id', $entry->id));
    }

    private function clearCategoryLinks(string $foreignKey, int $id): void
    {
        EventCategory::where($foreignKey, $id)->update(['task_source' => null]);
    }
}
