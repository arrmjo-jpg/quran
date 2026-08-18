<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

/**
 * The public season routes carry no auth middleware, so whatever their
 * resource exposes is exposed to the whole internet. They used to return
 * the same SeasonResource the admin endpoints do, which meant an
 * unauthenticated GET handed out the id of the admin who archived a
 * season along with the season's judging configuration.
 *
 * These tests pin the boundary in both directions: the public projection
 * must not regain an admin field, and the admin projection must not lose
 * one.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'public-exposure');

/**
 * Every field that must never appear on an unauthenticated response.
 *
 * @var array<int, string>
 */
const ADMIN_ONLY_SEASON_FIELDS = [
    'archived_by_user_id',
    'archive_reason',
    'archived_at',
    'frozen_at',
    'is_frozen',
    'participation_type_id',
    'tajweed_level_id',
];

function exposureAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'exposure-admin-'.Str::random(8).'@quran.test',
        'name' => 'Exposure Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/** A season carrying a value in every admin-only field, so nothing leaks by being null. */
function exposureSeason(string $adminId): string
{
    // Unique codes: both catalogs have a unique index on code, and some
    // tests below build more than one season.
    $participationType = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'adv-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);

    $season = SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'exposure-'.Str::random(8), 'year' => 2034,
        'registration_start' => '2034-01-01 00:00:00', 'registration_end' => '2034-01-15 00:00:00',
        'start_date' => '2034-01-16 00:00:00', 'end_date' => '2034-03-01 00:00:00',
        'status' => 'archived', 'is_active' => false,
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $participationType->id,
        'tajweed_level_id' => $tajweedLevel->id,
        'frozen_at' => '2034-01-02 00:00:00',
        'archived_at' => '2034-04-01 00:00:00',
        'archived_by_user_id' => $adminId,
        'archive_reason' => 'internal note nobody outside should read',
    ]);

    SeasonTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'season_id' => $season->id, 'locale' => 'en',
        'title' => 'Exposure Season', 'public_name' => 'Public Name',
    ]);

    return $season->id;
}

test('the unauthenticated season list leaks no admin-only field', function (): void {
    $admin = exposureAdmin();
    exposureSeason($admin->id);

    $season = $this->getJson('/api/v1/seasons')->assertStatus(200)->json('data.0');

    foreach (ADMIN_ONLY_SEASON_FIELDS as $field) {
        expect($season)->not->toHaveKey($field);
    }

    // And the admin's id must not turn up under any other key either.
    expect(json_encode($season))->not->toContain($admin->id);
});

test('the unauthenticated season detail leaks no admin-only field', function (): void {
    $admin = exposureAdmin();
    $seasonId = exposureSeason($admin->id);

    $season = $this->getJson("/api/v1/seasons/{$seasonId}")->assertStatus(200)->json('data');

    foreach (ADMIN_ONLY_SEASON_FIELDS as $field) {
        expect($season)->not->toHaveKey($field);
    }

    expect(json_encode($season))->not->toContain('internal note nobody outside should read');
});

test('the public projection still carries what a visitor actually needs', function (): void {
    $admin = exposureAdmin();
    $seasonId = exposureSeason($admin->id);

    $this->getJson("/api/v1/seasons/{$seasonId}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $seasonId)
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.year', 2034)
        // Eligibility: how a would-be entrant knows whether they can enter.
        ->assertJsonPath('data.min_age', 10)
        ->assertJsonPath('data.max_age', 18)
        ->assertJsonPath('data.translations.en.public_name', 'Public Name');
});

test('the admin projection keeps every field the public one drops', function (): void {
    $admin = exposureAdmin();
    $seasonId = exposureSeason($admin->id);

    $season = $this->actingAs($admin)
        ->getJson("/api/v1/admin/seasons/{$seasonId}")
        ->assertStatus(200)
        ->json('data');

    foreach (ADMIN_ONLY_SEASON_FIELDS as $field) {
        expect($season)->toHaveKey($field);
    }

    expect($season['archived_by_user_id'])->toBe($admin->id);
    expect($season['archive_reason'])->toBe('internal note nobody outside should read');
});

test('the admin season list is not reachable without authentication', function (): void {
    $admin = exposureAdmin();
    exposureSeason($admin->id);

    $this->getJson('/api/v1/admin/seasons')->assertStatus(401);
    $this->getJson('/api/v1/admin/seasons/'.exposureSeason($admin->id))->assertStatus(401);
});

test('the admin season list returns the full projection', function (): void {
    $admin = exposureAdmin();
    exposureSeason($admin->id);

    $this->actingAs($admin)->getJson('/api/v1/admin/seasons')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.archived_by_user_id', $admin->id);
});
