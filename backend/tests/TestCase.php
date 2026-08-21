<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DatabaseSafetyGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * The last moment anything can stop a run against the wrong database.
     *
     * HOOKED HERE AND NOT IN setUp(), and the difference matters. Laravel's
     * setUp() boots the application and then calls setUpTraits(), which is
     * where RefreshDatabase migrates — and migrating means dropping every
     * table on the resolved connection. A check placed after parent::setUp()
     * would run after the damage.
     *
     * refreshApplication() is called by setUp() before the traits, so this is
     * the earliest point at which the container exists and the connection can
     * be resolved, and the latest at which refusing still prevents anything.
     *
     * Repeated per test rather than once at bootstrap, because a test may
     * swap the connection itself — SeasonActivationConcurrencyTest opens a
     * real MySQL session to prove row locking.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        DatabaseSafetyGuard::assertSafe();
    }
}
