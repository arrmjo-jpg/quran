<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelTranslationModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

/**
 * The season rules endpoint takes participation_type_id and
 * tajweed_level_id, and the stage rules endpoint takes
 * judge_score_system_id — but nothing listed what those ids could be, so
 * an admin form had no way to offer a choice. These are the catalogs
 * behind those pickers.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'lookups');

function lookupAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'lookup-admin-'.Str::random(8).'@quran.test',
        'name' => 'Lookup Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

test('participation types lists active rows in display order with translations', function (): void {
    $admin = lookupAdmin();

    // Seeded out of order, so passing cannot be an accident of insertion.
    $second = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'female_only', 'display_order' => 2, 'is_active' => true]);
    $first = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    $retired = ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'retired', 'display_order' => 3, 'is_active' => false]);

    foreach ([$first, $second, $retired] as $model) {
        ParticipationTypeTranslationModel::query()->create([
            'id' => (string) Str::uuid(), 'participation_type_id' => $model->id,
            'locale' => 'en', 'name' => ucfirst($model->code),
        ]);
    }

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/participation-types')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.code', 'mixed')
        ->assertJsonPath('data.0.name.en', 'Mixed')
        ->assertJsonPath('data.0.display_order', 1)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.1.display_order', 2);

    // Exactly the agreed field set — nothing internal, no stray metadata.
    expect(array_keys($response->json('data.0')))
        ->toEqualCanonicalizing(['id', 'code', 'name', 'display_order']);

    // The retired option must not be offerable.
    expect(json_encode($response->json('data')))->not->toContain($retired->id);
});

test('tajweed levels lists active rows in display order', function (): void {
    $admin = lookupAdmin();

    $second = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 2, 'is_active' => true]);
    $first = TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'beginner', 'display_order' => 1, 'is_active' => true]);
    TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'retired', 'display_order' => 3, 'is_active' => false]);

    TajweedLevelTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'tajweed_level_id' => $first->id,
        'locale' => 'ar', 'name' => 'مبتدئ',
    ]);

    $this->actingAs($admin)->getJson('/api/v1/admin/tajweed-levels')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.name.ar', 'مبتدئ')
        ->assertJsonPath('data.1.id', $second->id);
});

test('judge score systems carry max_score, which the other catalogs do not', function (): void {
    $admin = lookupAdmin();

    $hundred = JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);
    JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'out_of_50', 'max_score' => 50, 'display_order' => 2, 'is_active' => true]);
    JudgeScoreSystemModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'retired', 'max_score' => 20, 'display_order' => 3, 'is_active' => false]);

    JudgeScoreSystemTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'judge_score_system_id' => $hundred->id,
        'locale' => 'en', 'name' => 'Out of 100',
    ]);

    $this->actingAs($admin)->getJson('/api/v1/admin/judge-score-systems')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'out_of_100')
        ->assertJsonPath('data.0.max_score', 100)
        ->assertJsonPath('data.0.display_order', 1)
        ->assertJsonPath('data.1.max_score', 50);

    $scoreSystem = $this->actingAs($admin)->getJson('/api/v1/admin/judge-score-systems')->json('data.0');
    expect(array_keys($scoreSystem))
        ->toEqualCanonicalizing(['id', 'code', 'name', 'display_order', 'max_score']);

    ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);

    $participationType = $this->actingAs($admin)->getJson('/api/v1/admin/participation-types')
        ->assertStatus(200)->json('data.0');

    expect($participationType)->not->toHaveKey('max_score');
});

test('every catalog is empty rather than broken when nothing is seeded', function (): void {
    $admin = lookupAdmin();

    foreach (['participation-types', 'tajweed-levels', 'judge-score-systems'] as $path) {
        $this->actingAs($admin)->getJson("/api/v1/admin/{$path}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
});

test('no catalog is reachable without authentication', function (): void {
    foreach (['participation-types', 'tajweed-levels', 'judge-score-systems'] as $path) {
        $this->getJson("/api/v1/admin/{$path}")->assertStatus(401);
    }
});

test('the ids a catalog returns are accepted by the endpoint that consumes them', function (): void {
    $admin = lookupAdmin();

    ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true]);
    TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create([
        'id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true,
    ]);

    $participationTypeId = $this->actingAs($admin)->getJson('/api/v1/admin/participation-types')->json('data.0.id');
    $tajweedLevelId = $this->actingAs($admin)->getJson('/api/v1/admin/tajweed-levels')->json('data.0.id');

    $seasonId = $this->actingAs($admin)->postJson('/api/v1/admin/seasons', [
        'slug' => 'lookup-'.Str::random(8), 'year' => 2035,
        'registration_start' => '2035-01-01T00:00:00+00:00',
        'registration_end' => '2035-01-15T00:00:00+00:00',
        'start_date' => '2035-01-16T00:00:00+00:00',
        'end_date' => '2035-03-01T00:00:00+00:00',
        'title_ar' => 'موسم', 'public_name_ar' => 'موسم القرآن',
        'title_en' => 'Season', 'public_name_en' => 'Quran Season',
        'title_es' => 'Temporada', 'public_name_es' => 'Temporada del Corán',
    ])->assertStatus(201)->json('data.id');

    // The whole point of the catalogs: an id taken straight from a picker
    // is directly usable by the endpoint it feeds.
    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $participationTypeId,
        'tajweed_level_id' => $tajweedLevelId,
        'country_ids' => [$country->id],
    ])->assertStatus(200)
        ->assertJsonPath('data.participation_type_id', $participationTypeId)
        ->assertJsonPath('data.tajweed_level_id', $tajweedLevelId);
});
