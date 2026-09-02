<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AppServiceProvider::boot() forces every generated URL to https in
 * production — the guarantee that /docs/mcp's endpoint URL (and every other
 * url()/route() call) is never accidentally shown as http:// behind a
 * reverse proxy whose X-Forwarded-Proto header goes missing or gets
 * misconfigured. Deliberately resets URL::forceScheme() and the faked env
 * in a finally block: PHPUnit runs this whole suite in one process (see
 * CLAUDE.md's memory_limit entry), so a forced scheme left in place would
 * silently leak into every test that runs after this one.
 */
class ProductionHttpsTest extends TestCase
{
    public function test_generated_urls_are_forced_to_https_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            (new AppServiceProvider($this->app))->boot();

            $this->assertStringStartsWith('https://', url('/'));
        } finally {
            URL::forceScheme(null);
            $this->app['env'] = 'testing';
        }
    }

    public function test_local_dev_urls_are_left_alone(): void
    {
        $this->assertStringStartsWith('http://', url('/'));
    }
}
