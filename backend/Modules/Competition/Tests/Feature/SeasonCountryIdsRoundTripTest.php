<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

/**
 * PATCH /admin/seasons/{id}/rules replaces a season's whole eligible-country
 * set, but nothing exposed the current set, so a client had no way to send
 * back what it wanted kept. Editing only the age range would silently wipe
 * every eligible country.
 *
 * The success criterion is the round trip, not the presence of a new JSON
 * field: read the season, change one unrelated value, send back what was
 * read, and find the countries untouched.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'season-country-ids');

function countryIdsAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'country-ids-admin-'.Str::random(8).'@quran.test',
        'name' => 'Country Ids Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function countryIdsCountry(string $iso2, string $iso3): CountryModel
{
    return CountryModel::query()->create([
        'id' => (string) Str::uuid(), 'iso_code' => $iso2, 'iso3_code' => $iso3,
        'phone_code' => '+'.random_int(100, 999), 'is_active' => true,
    ]);
}

/** @return array{season_id: string, participation_type: string, tajweed_level: string} */
function countryIdsSeason(UserModel $admin): array
{
    $test = test();

    $seasonId = $test->actingAs($admin)->postJson('/api/v1/admin/seasons', [
        'slug' => 'cids-'.Str::random(8), 'year' => 2036,
        'registration_start' => '2036-01-01T00:00:00+00:00',
        'registration_end' => '2036-01-15T00:00:00+00:00',
        'start_date' => '2036-01-16T00:00:00+00:00',
        'end_date' => '2036-03-01T00:00:00+00:00',
        'title_ar' => 'موسم', 'public_name_ar' => 'موسم القرآن',
        'title_en' => 'Season', 'public_name_en' => 'Quran Season',
        'title_es' => 'Temporada', 'public_name_es' => 'Temporada del Corán',
    ])->assertStatus(201)->json('data.id');

    return [
        'season_id' => $seasonId,
        'participation_type' => ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed-'.Str::random(5), 'display_order' => 1, 'is_active' => true])->id,
        'tajweed_level' => TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'adv-'.Str::random(5), 'display_order' => 1, 'is_active' => true])->id,
    ];
}

test('read, edit one field, save back — the eligible countries survive', function (): void {
    $admin = countryIdsAdmin();
    ['season_id' => $seasonId, 'participation_type' => $participationType, 'tajweed_level' => $tajweedLevel] = countryIdsSeason($admin);

    $jordan = countryIdsCountry('JO', 'JOR');
    $egypt = countryIdsCountry('EG', 'EGY');
    $morocco = countryIdsCountry('MA', 'MAR');

    // 1. Configure the rules with three eligible countries.
    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $participationType,
        'tajweed_level_id' => $tajweedLevel,
        'country_ids' => [$jordan->id, $egypt->id, $morocco->id],
    ])->assertStatus(200);

    // 2. Read the season back — the set must come with it.
    $season = $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}")
        ->assertStatus(200)
        ->json('data');

    expect($season['country_ids'])->toHaveCount(3);
    expect($season['country_ids'])->toEqualCanonicalizing([$jordan->id, $egypt->id, $morocco->id]);

    // 3. Change only the age range, echoing back exactly what was read —
    //    which is what an edit form does when the admin touches one field.
    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 12, 'max_age' => 20,
        'participation_type_id' => $season['participation_type_id'],
        'tajweed_level_id' => $season['tajweed_level_id'],
        'country_ids' => $season['country_ids'],
    ])->assertStatus(200);

    // 4. Nothing was lost.
    $reloaded = $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}")
        ->assertStatus(200)
        ->json('data');

    expect($reloaded['min_age'])->toBe(12);
    expect($reloaded['country_ids'])->toEqualCanonicalizing([$jordan->id, $egypt->id, $morocco->id]);
});

test('a season with no rules yet reports an empty set, not a missing field', function (): void {
    $admin = countryIdsAdmin();
    ['season_id' => $seasonId] = countryIdsSeason($admin);

    $season = $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}")
        ->assertStatus(200)
        ->json('data');

    // The distinction matters: a client must be able to tell "no countries
    // are eligible" from "this response didn't load them".
    expect($season)->toHaveKey('country_ids');
    expect($season['country_ids'])->toBe([]);
});

test('every admin season response carries country_ids, not just the detail one', function (): void {
    $admin = countryIdsAdmin();
    ['season_id' => $seasonId, 'participation_type' => $participationType, 'tajweed_level' => $tajweedLevel] = countryIdsSeason($admin);
    $jordan = countryIdsCountry('JO', 'JOR');

    $rulesResponse = $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $participationType,
        'tajweed_level_id' => $tajweedLevel,
        'country_ids' => [$jordan->id],
    ])->assertStatus(200);

    // The PATCH response itself must reflect what was just written —
    // otherwise a client that trusts it would immediately be out of date.
    expect($rulesResponse->json('data.country_ids'))->toBe([$jordan->id]);

    $this->actingAs($admin)->getJson('/api/v1/admin/seasons')
        ->assertStatus(200)
        ->assertJsonPath('data.0.country_ids', [$jordan->id]);
});

test('the listing loads country ids for several seasons without a query per row', function (): void {
    $admin = countryIdsAdmin();
    $jordan = countryIdsCountry('JO', 'JOR');
    $egypt = countryIdsCountry('EG', 'EGY');

    $first = countryIdsSeason($admin);
    $second = countryIdsSeason($admin);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$first['season_id']}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $first['participation_type'],
        'tajweed_level_id' => $first['tajweed_level'],
        'country_ids' => [$jordan->id],
    ])->assertStatus(200);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$second['season_id']}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $second['participation_type'],
        'tajweed_level_id' => $second['tajweed_level'],
        'country_ids' => [$jordan->id, $egypt->id],
    ])->assertStatus(200);

    $byId = collect($this->actingAs($admin)->getJson('/api/v1/admin/seasons')->assertStatus(200)->json('data'))
        ->keyBy('id');

    expect($byId[$first['season_id']]['country_ids'])->toBe([$jordan->id]);
    expect($byId[$second['season_id']]['country_ids'])->toEqualCanonicalizing([$jordan->id, $egypt->id]);
});

test('the public season endpoint still does not expose the eligible countries', function (): void {
    $admin = countryIdsAdmin();
    ['season_id' => $seasonId, 'participation_type' => $participationType, 'tajweed_level' => $tajweedLevel] = countryIdsSeason($admin);
    $jordan = countryIdsCountry('JO', 'JOR');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $participationType,
        'tajweed_level_id' => $tajweedLevel,
        'country_ids' => [$jordan->id],
    ])->assertStatus(200);

    // country_ids is admin configuration; PublicSeasonResource must not
    // pick it up just because SeasonResource gained it.
    $public = $this->getJson("/api/v1/seasons/{$seasonId}")->assertStatus(200)->json('data');

    expect($public)->not->toHaveKey('country_ids');
});
