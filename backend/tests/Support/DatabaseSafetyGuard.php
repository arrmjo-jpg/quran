<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refuses to let the suite run against a database it must never touch.
 *
 * WHY THIS EXISTS AT ALL. `phpunit.xml` once pointed the whole suite —
 * RefreshDatabase included — at the real development database and wiped it.
 * The cause is worth restating, because it is the reason a name check alone
 * is not protection: `<env force="true">` only overrides `getenv()` and
 * `$_ENV`, while the Docker container injects `DB_CONNECTION=mysql` and
 * `DB_DATABASE=quran_platform` into `$_SERVER` — and Laravel's `env()` reads
 * `$_SERVER` first. Every declared override was silently losing to the
 * container.
 *
 * `bootstrap-testing-env.php` now writes all three superglobals before Laravel
 * boots, which fixes that particular hole. This class exists because a fix in
 * one file is not a guarantee: it checks the connection Laravel ACTUALLY
 * resolved, at the last moment before a test can migrate anything.
 *
 * IT DOES NOT TRUST THE DATABASE NAME BEING DIFFERENT. Three independent
 * conditions must all hold, so that no single mistake — a stale env var, an
 * edited config, a forgotten override — is enough to reach real data.
 */
final class DatabaseSafetyGuard
{
    /**
     * The only databases a test run may ever be pointed at.
     *
     * An allowlist, not a denylist. A denylist protects the names somebody
     * thought of; this refuses everything nobody has vouched for, which is
     * the direction that fails safe when a new environment appears.
     */
    private const ALLOWED = [
        ':memory:',
        'quran_platform_test',
    ];

    /**
     * Databases that must never be reachable from a test, whatever else is
     * true. Redundant with the allowlist by design — the belt to its braces,
     * and the line a future edit is most likely to trip over.
     */
    private const FORBIDDEN = [
        'quran_platform',
    ];

    public static function assertSafe(): void
    {
        $environment = app()->environment();

        if ($environment !== 'testing') {
            self::refuse(
                "APP_ENV resolved to '{$environment}', not 'testing'.",
                'The suite only runs in the testing environment. Something is '
                .'overriding it — check tests/bootstrap-testing-env.php and the '
                .'container environment.'
            );
        }

        $database = (string) DB::connection()->getDatabaseName();

        // Names are compared on their basename: a SQLite connection resolves
        // to a full path, and 'database/quran_platform.sqlite' must not slip
        // past a check written for 'quran_platform'.
        $basename = basename($database);

        foreach (self::FORBIDDEN as $forbidden) {
            if ($database === $forbidden || $basename === $forbidden || $basename === $forbidden.'.sqlite') {
                self::refuse(
                    "The suite resolved to '{$database}', which is a real database.",
                    'RefreshDatabase would have dropped every table in it. This has '
                    .'happened before — see the note in phpunit.xml.'
                );
            }
        }

        if (! in_array($database, self::ALLOWED, true) && ! in_array($basename, self::ALLOWED, true)) {
            self::refuse(
                "The suite resolved to '{$database}', which is not an approved test database.",
                'Approved: '.implode(', ', self::ALLOWED).'. If you are adding a new '
                .'test database, add it to DatabaseSafetyGuard::ALLOWED deliberately '
                .'— that edit is the review step.'
            );
        }
    }

    /**
     * Aborts the process rather than failing the test.
     *
     * A failed assertion is caught, reported and the run continues to the
     * next test — which, if the connection is wrong, is the next test to drop
     * the tables. Nothing after this point is safe, so nothing after this
     * point runs.
     */
    private static function refuse(string $what, string $why): never
    {
        $message = "\n\n".str_repeat('=', 72)."\n"
            ."  REFUSING TO RUN THE TEST SUITE\n"
            .str_repeat('=', 72)."\n\n"
            ."  {$what}\n\n"
            ."  {$why}\n\n"
            .str_repeat('=', 72)."\n\n";

        fwrite(STDERR, $message);

        // Thrown as well as printed: a caller that has captured output still
        // gets something to report, and the exception carries the reason.
        throw new RuntimeException(trim(preg_replace('/\s+/', ' ', $what.' '.$why) ?? $what));
    }
}
