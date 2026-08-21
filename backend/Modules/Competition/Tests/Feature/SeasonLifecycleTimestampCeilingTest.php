<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;

uses(RefreshDatabase::class)->group('competition', 'feature', 'schema');

/*
|--------------------------------------------------------------------------
| Y2038 on the season lifecycle columns
|--------------------------------------------------------------------------
|
| `frozen_at` and `archived_at` were declared TIMESTAMP one day after the
| same table's four date columns were moved off TIMESTAMP for exactly this
| reason. MySQL caps TIMESTAMP at 2038-01-19 03:14:07 UTC; a season archived
| in 2040 is a row it refuses.
|
| THE ROUND-TRIP TEST BELOW IS THE REAL ONE, and it is deliberately not
| driver-gated. On SQLite it passes trivially — type affinity has no ceiling —
| and that is the point: it will start doing real work the moment the suite
| runs on MySQL, without anyone having to remember to write it then. A test
| that skipped on SQLite would prove nothing today and be forgotten tomorrow.
|
| The column-type assertion underneath it IS gated, because reading
| information_schema is meaningless on a driver that has no such column.
*/

/** @param array<string, mixed> $overrides */
function ceilingSeason(array $overrides = []): SeasonModel
{
    return SeasonModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'slug' => 'ceiling-'.Str::random(8),
        'year' => 2040,
        'registration_start' => '2040-01-01 00:00:00',
        'registration_end' => '2040-01-15 00:00:00',
        'start_date' => '2040-01-16 00:00:00',
        'end_date' => '2040-03-01 00:00:00',
        'status' => 'draft',
        'is_active' => false,
    ], $overrides));
}

test('a season frozen after 2038 round-trips', function (): void {
    $season = ceilingSeason(['frozen_at' => '2040-06-01 12:00:00']);

    $stored = SeasonModel::query()->findOrFail($season->id);

    expect($stored->frozen_at)->not->toBeNull();
    expect($stored->frozen_at->format('Y-m-d H:i:s'))->toBe('2040-06-01 12:00:00');
});

test('a season archived after 2038 round-trips', function (): void {
    // RestoreSeasonTest has been writing exactly this value since it was
    // written, and it only ever passed because SQLite has no ceiling.
    $season = ceilingSeason([
        'status' => 'archived',
        'archived_at' => '2040-04-01 00:00:00',
        'archive_reason' => 'cancelled by mistake',
    ]);

    $stored = SeasonModel::query()->findOrFail($season->id);

    expect($stored->archived_at->format('Y-m-d H:i:s'))->toBe('2040-04-01 00:00:00');
});

test('the far edge the platform allows is storable', function (): void {
    // CreateSeasonRequest validates `year` up to 2100, so that is the bound
    // the schema has to honour — not some comfortable margin past 2038.
    $season = ceilingSeason([
        'year' => 2100,
        'registration_start' => '2100-01-01 00:00:00',
        'registration_end' => '2100-01-15 00:00:00',
        'start_date' => '2100-01-16 00:00:00',
        'end_date' => '2100-03-01 00:00:00',
        'frozen_at' => '2100-02-01 00:00:00',
        'archived_at' => '2100-12-31 23:59:59',
    ]);

    $stored = SeasonModel::query()->findOrFail($season->id);

    expect($stored->frozen_at->format('Y'))->toBe('2100');
    expect($stored->archived_at->format('Y'))->toBe('2100');
});

test('both lifecycle columns are DATETIME, like the four beside them', function (): void {
    $types = collect(DB::select(
        'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS '
        .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
        ."AND COLUMN_NAME IN ('registration_start', 'start_date', 'frozen_at', 'archived_at')",
        ['seasons']
    ))->pluck('DATA_TYPE', 'COLUMN_NAME');

    // Asserted against the two that were already correct, rather than against
    // the literal 'datetime': what matters is that the six columns of one
    // table agree, which is what stopped being true on 2026-08-06.
    expect($types['frozen_at'])->toBe($types['registration_start']);
    expect($types['archived_at'])->toBe($types['start_date']);
    expect($types['frozen_at'])->toBe('datetime');
})->skip(
    fn (): bool => DB::connection()->getDriverName() !== 'mysql',
    'Column types are a MySQL concern; SQLite has type affinity and no TIMESTAMP ceiling. The round-trip tests above cover the behaviour on every driver.'
);
