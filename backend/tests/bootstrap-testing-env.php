<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
 * phpunit.xml's <env force="true"> only overrides getenv()/$_ENV. The
 * quran_platform_app Docker container injects DB_CONNECTION=mysql (and
 * friends) as real process environment variables, which PHP also copies
 * into $_SERVER — and Laravel's env() helper reads $_SERVER first, so the
 * container's real values won regardless of force="true", silently
 * pointing the whole suite (RefreshDatabase included) at the real dev
 * database instead of sqlite. Overriding $_SERVER directly here, before
 * Laravel ever boots, is the only override Laravel's env() can't see
 * through.
 */
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_FOREIGN_KEYS' => 'false',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
