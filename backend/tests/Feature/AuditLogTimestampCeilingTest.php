<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('core', 'feature', 'schema', 'security');

/*
|--------------------------------------------------------------------------
| Y2038 on audit_logs.created_at — ADR-018 D9
|--------------------------------------------------------------------------
|
| Same ceiling, same reasoning and the same shape of test as
| SeasonLifecycleTimestampCeilingTest: MySQL's TIMESTAMP stops at
| 2038-01-19 03:14:07 UTC and refuses anything past it with error 1292.
|
| It matters on THIS table because ADR-018 D2 makes it the login history —
| a screen people filter by date. ADR-017 chose DATETIME for activity_logs
| for the same reason, and two log tables disagreeing about how far time
| goes is a difference nobody could explain later.
|
| The round-trip is deliberately not driver-gated: on SQLite it passes
| trivially, because type affinity has no ceiling, and it starts doing real
| work the moment the suite runs on MySQL without anyone remembering to
| write it then. The column-type assertion IS gated — information_schema
| means nothing on a driver that has no such column.
*/

test('an audit row can be written past 2038', function (): void {
    $id = (string) Uuid::v7();

    // Written through the query builder rather than the model: created_at is
    // deliberately not fillable -- the database default writes it -- and the
    // subject here is the column's range, not the model's mass-assignment
    // rules.
    DB::table('audit_logs')->insert([
        'id' => $id,
        'execution_duration_ms' => 5,
        'method' => 'POST',
        'path' => 'api/v1/admin/auth/login',
        'route_name' => 'admin.auth.login',
        'response_status' => 200,
        'created_at' => '2040-06-01 12:00:00',
    ]);

    $stored = DB::table('audit_logs')->where('id', $id)->value('created_at');

    expect((string) $stored)->toStartWith('2040-06-01 12:00:00');
});

test('created_at is DATETIME, not TIMESTAMP', function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Column types are a MySQL question; SQLite has type affinity.');
    }

    $type = DB::selectOne(
        'SELECT DATA_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['audit_logs', 'created_at']
    );

    expect(strtolower((string) $type->DATA_TYPE))->toBe('datetime');
});
