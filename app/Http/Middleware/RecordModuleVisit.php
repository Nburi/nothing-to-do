<?php

namespace App\Http\Middleware;

use App\Models\FeatureAnnouncement;
use App\Models\ModuleVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps a ModuleVisit row whenever a signed-in user's request lands on one
 * of FeatureAnnouncement::scopableModules()'s pages — the real usage signal
 * behind an announcement's "only for module users" audience scoping (see
 * FeatureAnnouncement::isModuleInUseBy()).
 *
 * Registered globally on the 'web' middleware group (bootstrap/app.php)
 * rather than attached to each module's route individually: it derives the
 * module key from the current route name via FeatureAnnouncement's own
 * reverse lookup, so it no-ops on every route that isn't a scopable module's
 * page (including the admin/API/livewire-update routes) and needs no
 * per-route wiring — a future module becomes trackable automatically the
 * moment it's added to scopableModules(), nothing here has to change.
 *
 * Fires only on the real page-load GET request to a module's own named
 * route — a wire:navigate SPA jump still re-hits that route server-side
 * (confirmed by this app's existing session-flash-read-once behaviour in
 * FeatureAnnouncementToast, which relies on the same fact), but a Livewire
 * action afterwards goes to /livewire/update instead, which never matches a
 * module route name and is therefore a no-op here.
 */
class RecordModuleVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $moduleKey = FeatureAnnouncement::moduleKeyForRouteName($request->route()?->getName());

            if ($moduleKey !== null) {
                ModuleVisit::query()->updateOrCreate([
                    'user_id' => $user->id,
                    'module_key' => $moduleKey,
                ]);
            }
        }

        return $next($request);
    }
}
