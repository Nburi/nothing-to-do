<?php

namespace App\Providers;

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
    }
}
