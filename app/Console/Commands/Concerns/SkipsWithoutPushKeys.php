<?php

namespace App\Console\Commands\Concerns;

use App\Services\PushNotifier;
use Illuminate\Support\Facades\Log;

/**
 * For scheduled commands whose only job is to send pushes. A server without VAPID keys cannot send
 * any, so the tick is skipped with one line instead of failing every minute. Nothing is stamped as
 * "sent", so the notifications still go out once the keys are set.
 */
trait SkipsWithoutPushKeys
{
    protected function skipWithoutPushKeys(): bool
    {
        if (PushNotifier::isConfigured()) {
            return false;
        }

        $this->warn('Skipped: no VAPID keys configured, push is off on this server.');
        Log::debug($this->getName().' skipped: no VAPID keys configured.');

        return true;
    }
}
