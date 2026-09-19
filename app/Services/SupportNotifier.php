<?php

namespace App\Services;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Push notifications for the support channel: every admin hears about a new
 * request, and the submitter hears when it gets an answer. Deliberately not
 * toggleable in Settings (product decision) — it only reaches devices that
 * already subscribed to push at all. Fired from SupportRequest's model events
 * so no creation/answer path can forget it.
 *
 * Best-effort by design: a failed push must never break saving a request or
 * an answer, so everything here is swallowed after being logged.
 */
class SupportNotifier
{
    public function notifyAdminsOfNewRequest(SupportRequest $request): void
    {
        $this->guard(function () use ($request) {
            $request->loadMissing('user');

            $admins = User::query()
                ->where('is_admin', true)
                ->where('id', '!=', $request->user_id)
                ->whereHas('pushSubscriptions')
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $payload = [
                'title' => $request->type === 'support' ? 'Neue Support-Anfrage' : 'Neues Feedback',
                'body' => ($request->user?->name ?? 'Jemand').': '.Str::limit($request->subject, 100),
                'url' => route('admin.support', absolute: false),
            ];

            $notifier = app(PushNotifier::class);

            foreach ($admins as $admin) {
                $notifier->notify($admin, $payload);
            }
        });
    }

    public function notifyUserOfResponse(SupportRequest $request): void
    {
        $this->guard(function () use ($request) {
            // An admin answering their own request doesn't need a push about it.
            if ($request->responded_by === $request->user_id) {
                return;
            }

            $user = $request->user;

            if ($user === null || ! $user->pushSubscriptions()->exists()) {
                return;
            }

            app(PushNotifier::class)->notify($user, [
                'title' => 'Antwort auf deine Anfrage',
                'body' => Str::limit($request->subject, 60).' — '.Str::limit((string) $request->response, 90),
                'url' => route('support', absolute: false),
            ]);
        });
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Support push notification failed', ['error' => $e->getMessage()]);
        }
    }
}
