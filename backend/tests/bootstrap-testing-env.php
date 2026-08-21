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
 *
 * That fix is necessary and not sufficient — a single file getting this
 * right is not a guarantee. Tests\Support\DatabaseSafetyGuard re-checks the
 * connection Laravel ACTUALLY resolved, per test, and aborts the process
 * before RefreshDatabase can migrate anything.
 */

/*
 * WHICH ENGINE, AND WHY IT IS A CHOICE RATHER THAN A DEFAULT.
 *
 * SQLite :memory: stays the default because it needs no container and keeps
 * a local run fast. MySQL is opted into with TEST_DB=mysql, and CI does
 * exactly that.
 *
 * The reason CI does is measured, not assumed. Running the full suite on
 * both after this epic's fixes:
 *
 *   sqlite :memory: with foreign keys   1002 passed, 5 skipped   ~861s
 *   mysql quran_platform_test           1002 passed, 4 skipped   ~944s
 *
 * ~10% slower, and it catches two classes of defect SQLite cannot express:
 *
 *   COLUMN TYPES. MySQL's TIMESTAMP caps at 2038; SQLite has type affinity
 *   and no ceiling. seasons.frozen_at and archived_at were TIMESTAMP for two
 *   weeks, and RestoreSeasonTest had been writing 2040 dates into them the
 *   whole time. Only MySQL could say so.
 *
 *   JSON KEY ORDER. SQLite stores JSON as text and returns keys in insertion
 *   order; MySQL's binary JSON orders them itself. A profile test asserted
 *   exact array equality on social_links and passed for that reason alone.
 *
 * The skip difference is the same fact from the other side: the column-type
 * assertion in SeasonLifecycleTimestampCeilingTest runs on MySQL and skips
 * on SQLite.
 */
$useMysql = ($_SERVER['TEST_DB'] ?? getenv('TEST_DB') ?: '') === 'mysql';

$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => $useMysql ? 'mysql' : 'sqlite',

    // Never the development database. DatabaseSafetyGuard allowlists exactly
    // these two and aborts on anything else, including quran_platform.
    'DB_DATABASE' => $useMysql ? 'quran_platform_test' : ':memory:',

    'DB_URL' => '',

    // ENFORCED, as Laravel's own default already is.
    //
    // This read 'false' from the first commit of the repository, arriving in
    // phpunit.xml alongside the DB_CONNECTION and DB_DATABASE lines that did
    // have a reason, and was copied here verbatim when those moved. No ADR,
    // no commit message and no comment ever justified it — it was inherited,
    // not decided.
    //
    // What it cost: the schema these migrations build carries 76 foreign keys
    // with delete rules identical to production, and none of them were being
    // enforced. A test could insert a membership pointing at a centre that had
    // been rolled back, or an appeal against an application that never
    // existed, and the suite would agree. Both were happening, and a third —
    // a contestant photo pointing at no media asset — was found the moment
    // this flipped.
    'DB_FOREIGN_KEYS' => 'true',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
