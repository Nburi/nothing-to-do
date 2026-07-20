<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    // These are plain static files under public/, served directly by the webserver
    // and never routed through Laravel (see CLAUDE.md §7 API/PWA notes) — so this
    // checks disk presence/content rather than an HTTP round trip, which would 404
    // here regardless since PHPUnit's test client dispatches through the router only.
    public function test_manifest_exists_and_is_valid(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertSame('nothing-to-do', $manifest['name']);
        $this->assertNotEmpty($manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_service_worker_exists(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_offline_page_exists(): void
    {
        $this->assertFileExists(public_path('offline.html'));
    }
}
