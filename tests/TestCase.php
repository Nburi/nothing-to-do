<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deferred callbacks (e.g. Pomodoro pushes sent after the response)
        // would otherwise never run inside a test, which has no real response.
        $this->withoutDefer();
    }
}
