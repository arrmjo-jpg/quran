<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;

uses()->group('competition', 'feature', 'season-use-cases', 'concurrency');

/**
 * Proves ADR-005 Decision 16 ("seasons.is_current: Activating a season —
 * Pessimistic Locking, lock all candidate rows") is a real row-level DB
 * lock, not just a method call that happens to compile.
 *
 * SQLite has no FOR UPDATE semantics (Laravel's grammar silently drops
 * the clause), so this can only be proven against a real MySQL
 * connection. It intentionally does NOT use RefreshDatabase: proving a
 * lock blocks a *second, independent* connection requires the seeded row
 * to be actually committed and visible outside the test's own
 * transaction, which RefreshDatabase's wrapping transaction would
 * prevent. Cleanup is manual instead (see finally block).
 *
 * It also does NOT use the app's default connection: phpunit.xml forces
 * DB_CONNECTION/DB_DATABASE to sqlite (so the rest of the suite can never
 * accidentally hit the real dev database — see phpunit.xml comment). This
 * test instead builds its own 'season_lock_*' connections pointing at the
 * dedicated quran_platform_test MySQL database, and temporarily swaps
 * config('database.default') so SeasonModel/the repository (which both
 * resolve the connection dynamically per-call) use it for the duration of
 * the test only.
 *
 * Technique: open a genuinely separate MySQL session, give it a 1-second
 * innodb_lock_wait_timeout, and have it attempt the same FOR UPDATE lock
 * while connection #1's transaction still holds it, uncommitted. If the
 * lock is real, connection #2 blocks and then fails with "Lock wait
 * timeout exceeded" — that failure is the proof. If the repository
 * method were a plain SELECT (no locking), connection #2 would return
 * immediately instead, and the test would fail.
 */
function seasonLockTestMysqlConfig(): array
{
    return array_merge(config('database.connections.mysql'), ['database' => 'quran_platform_test']);
}

function seasonLockTestMysqlReachable(): bool
{
    try {
        config(['database.connections.season_lock_probe_reachability' => seasonLockTestMysqlConfig()]);
        DB::connection('season_lock_probe_reachability')->getPdo();

        return true;
    } catch (\Throwable) {
        return false;
    } finally {
        DB::purge('season_lock_probe_reachability');
    }
}

test('findOrFailForActivation takes a real row lock that blocks a concurrent activation attempt on MySQL', function (): void {
    $seasonId = (string) Str::uuid();

    config(['database.connections.season_lock_main' => seasonLockTestMysqlConfig()]);
    config(['database.connections.season_lock_probe' => seasonLockTestMysqlConfig()]);

    $originalDefaultConnection = config('database.default');
    config(['database.default' => 'season_lock_main']);

    try {
        SeasonModel::query()->create([
            'id' => $seasonId,
            'slug' => 'lock-probe-'.Str::random(8),
            'year' => 2099,
            'registration_start' => '2099-01-01 00:00:00',
            'registration_end' => '2099-01-15 00:00:00',
            'start_date' => '2099-01-16 00:00:00',
            'end_date' => '2099-03-01 00:00:00',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $probe = DB::connection('season_lock_probe');
        $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::connection('season_lock_main')->beginTransaction();
        app(SeasonRepositoryContract::class)->findOrFailForActivation($seasonId);

        $blockedByLock = false;

        try {
            $probe->table('seasons')->lockForUpdate()->get(['id']);
        } catch (QueryException $exception) {
            $blockedByLock = str_contains($exception->getMessage(), 'Lock wait timeout exceeded');
        }

        DB::connection('season_lock_main')->rollBack();

        expect($blockedByLock)->toBeTrue();
    } finally {
        config(['database.default' => $originalDefaultConnection]);
        DB::connection('season_lock_main')->table('seasons')->where('id', $seasonId)->delete();
        DB::purge('season_lock_main');
        DB::purge('season_lock_probe');
    }
})->skip(
    fn () => ! seasonLockTestMysqlReachable(),
    'Pessimistic row locking can only be proven against a real MySQL connection (run inside the Docker stack, against the dedicated quran_platform_test database, not the default sqlite suite).'
);
