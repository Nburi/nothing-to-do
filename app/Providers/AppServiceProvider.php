<?php

namespace App\Providers;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\TaskGroup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Set in register(), not boot(): Passport registers its routes from
        // its own boot(), and by then this flag decides whether the
        // /oauth/device/* routes exist at all. Claude never uses the device
        // grant — it is a browser-redirect client — so those routes would
        // only be unreviewed surface area.
        Passport::$deviceCodeGrantEnabled = false;

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

        $this->configureMcpOAuth();

        // A category's task link points at a project/group/Agenda entry. The FK itself is
        // nullOnDelete, but that only nulls the FK — `task_source` would keep saying "project"
        // with nothing behind it, so the link sheet showed an active chip over an empty picker.
        // Reset `task_source` here, while the FK still identifies the affected categories (the
        // DB nulls it during the delete itself, i.e. after `deleting` but before `deleted`).
        Project::deleting(fn (Project $project) => $this->clearCategoryLinks('linked_project_id', $project->id));
        TaskGroup::deleting(fn (TaskGroup $group) => $this->clearCategoryLinks('linked_group_id', $group->id));
        AgendaEntry::deleting(fn (AgendaEntry $entry) => $this->clearCategoryLinks('linked_agenda_entry_id', $entry->id));
    }

    /**
     * Passport exists in this app for exactly one reason: claude.ai and
     * Claude Desktop can only authenticate a custom MCP connector via OAuth
     * (see routes/ai.php). Everything about it is therefore narrowed down to
     * that job — no self-service client-management API, and a consent screen
     * that looks like the rest of the product instead of Passport's stock one.
     */
    private function configureMcpOAuth(): void
    {
        // Passport ships JSON routes under /oauth/* for managing clients,
        // scopes and personal access tokens from a first-party SPA. This app
        // has no such SPA — token management lives in Settings and goes
        // through Sanctum — so leaving them registered would be unreviewed
        // surface area for no gain.
        Passport::$registersJsonApiRoutes = false;

        // Claude refreshes reactively on a 401 and keeps a connection alive
        // for a long time, so a short access token plus a long refresh token
        // is the shape that fits: revoking a connection takes effect within
        // the hour instead of whenever a long-lived token happens to expire.
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addMonths(6));

        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));

        // The throttle:mcp middleware Mcp::web() is given in routes/ai.php.
        // Generous enough that a model working through a list of tasks never
        // trips it, low enough to bound a runaway agent loop.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }

    private function clearCategoryLinks(string $foreignKey, int $id): void
    {
        EventCategory::where($foreignKey, $id)->update(['task_source' => null]);
    }
}
