<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

uses(RefreshDatabase::class)->group('contestants', 'feature', 'identity');

/*
|--------------------------------------------------------------------------
| Identity 360 — Epic 4 Story 2 (ADR-016 D16, D18, D19, D20)
|--------------------------------------------------------------------------
|
| The endpoint composes four things a contestant links to. What is worth
| testing is not that each one arrives — that is one eager load and a map —
| but the three decisions that are easy to get wrong later:
|
|   D18  exactly four user fields cross, and email is not one of them
|   D19  national_id does NOT cross, even though the caller holds the
|        permission that reveals it one route over
|   D20  a branch withheld for lack of permission is distinguishable from a
|        branch that is genuinely empty
|
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function idnUser(string $type = UserType::ADMIN, array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'idn-'.Str::random(10).'@quran.test',
        'name' => 'Identity Test',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ], $overrides));
}

function idnAs(string $roleName): UserModel
{
    $user = idnUser();
    $role = app(RoleRepositoryContract::class)->findByName($roleName);

    DB::table('role_user')->insert(['role_id' => $role->id->value, 'user_id' => (string) $user->id]);

    app(EffectivePermissionResolver::class)->forget(new UserId((string) $user->id));

    return $user->fresh();
}

/**
 * A country with the three translations the panel ships.
 *
 * BOTH ISO codes are generated, not just iso3. Several tests here need two
 * countries at once — the query-count test builds two contestants — and
 * `countries.iso_code` and `iso3_code` are each UNIQUE. Fixing only iso3
 * moved the collision rather than removing it.
 *
 * The generated iso2 is written to $iso2Out so a caller asserting the
 * ISO-code fallback can compare against what was actually stored instead of
 * hardcoding a value this function no longer promises.
 */
function idnCountry(array $names = ['ar' => 'الأردن', 'en' => 'Jordan', 'es' => 'Jordania'], ?string & $iso2Out = null): string
{
    static $sequence = 0;
    $sequence++;

    $id = (string) Str::uuid();
    // Two letters from a counter, so the codes are unique and stable within
    // a test rather than randomly colliding one run in fifty.
    $iso2Out = chr(65 + intdiv($sequence, 26) % 26).chr(65 + $sequence % 26);

    DB::table('countries')->insert([
        'id' => $id,
        'iso_code' => $iso2Out,
        'iso3_code' => $iso2Out.chr(65 + $sequence % 26),
        'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ($names as $locale => $name) {
        DB::table('country_translations')->insert([
            'id' => (string) Str::uuid(),
            'country_id' => $id,
            'locale' => $locale,
            'name' => $name,
        ]);
    }

    return $id;
}

/** @return array{0: string, 1: UserModel} contestant id, its account */
function idnContestant(array $overrides = [], ?UserModel $account = null): array
{
    $account ??= idnUser(UserType::CONTESTANT, ['name' => 'Account Name']);
    $id = (string) Str::uuid();

    // Only made when the caller did not bring one. array_merge would build
    // the default either way, so a test passing its own country_id would
    // still create a second country — and collide on iso3_code.
    $countryId = $overrides['country_id'] ?? idnCountry();

    DB::table('contestants')->insert(array_merge([
        'id' => $id,
        'user_id' => (string) $account->id,
        'country_id' => $countryId,
        'full_name' => 'Contestant Full Name',
        'date_of_birth' => '1999-01-01',
        'gender' => 'male',
        'national_id' => '9990000001',
        'phone_number' => '+962790000000',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return [$id, $account];
}

/** A centre with one circle, returned as [centerId, circleId]. */
function idnCircle(string $countryId, string $centerName = 'Amman Centre', string $circleName = 'Morning Circle'): array
{
    $centerId = (string) Str::uuid();
    DB::table('centers')->insert([
        'id' => $centerId, 'name' => $centerName, 'country_id' => $countryId,
        'city' => 'Amman', 'address' => '1 Test Street',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $circleId = (string) Str::uuid();
    DB::table('circles')->insert([
        'id' => $circleId, 'center_id' => $centerId, 'name' => $circleName,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$centerId, $circleId];
}

function idnMembership(string $contestantId, string $circleId, ?string $joinedAt = null, ?string $leftAt = null, ?string $reason = null): string
{
    $id = (string) Str::uuid();

    DB::table('contestant_memberships')->insert([
        'id' => $id,
        'contestant_id' => $contestantId,
        'circle_id' => $circleId,
        'joined_at' => $joinedAt ?? now()->subMonths(6),
        'left_at' => $leftAt,
        'reason' => $reason,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('identity requires authentication', function (): void {
    [$contestantId] = idnContestant();

    $this->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertStatus(401);
});

test('identity is refused to an admin without contestants.view', function (string $roleName): void {
    [$contestantId] = idnContestant();

    $this->actingAs(idnAs($roleName))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertStatus(403);
})->with(['judge', 'evaluator', 'moderator']);

test('a contestant-typed account is refused the admin surface', function (): void {
    [$contestantId] = idnContestant();

    $this->actingAs(idnUser(UserType::CONTESTANT))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertStatus(403);
});

test('every role holding contestants.view may read identity', function (string $roleName): void {
    [$contestantId] = idnContestant();

    $this->actingAs(idnAs($roleName))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertStatus(200);
})->with(['super_admin', 'competition_manager', 'data_entry']);

/*
|--------------------------------------------------------------------------
| Shape
|--------------------------------------------------------------------------
*/

test('the payload carries exactly the five branches D19 defines', function (): void {
    [$contestantId] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // A closed set, not a subset. A branch added later without a decision
    // behind it should fail here rather than quietly ship.
    expect(array_keys($response->json('data')))
        ->toEqualCanonicalizing(['contestant', 'user', 'country', 'memberships', 'withheld']);
});

test('GM [SUPERSEDED BY STORY 4]: the branch carried ten keys before the photo and the age', function (): void {
    [$contestantId] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // Recorded before Story 4 widens it (ADR-016 D24), as a closed set. The
    // five top-level branches were already pinned above; the contestant
    // branch's own keys were not, so `age` and `photo` could have been added
    // without a single expectation moving.
    // Kept as the BEFORE half of the pair, asserting that the ten original
    // keys all survived. Deleting it would leave the widening recorded only
    // by the test describing the new shape.
    expect(array_keys($response->json('data.contestant')))->toContain(
        'id', 'user_id', 'country_id', 'full_name', 'date_of_birth',
        'gender', 'phone_number', 'photo_media_asset_id', 'is_deleted',
        'profile_completeness',
    );
});

test('GM [CHANGED IN STORY 4]: the photo id now resolves beside itself', function (): void {
    // A real asset, not an invented uuid. `contestants.photo_media_id` is a
    // foreign key, and this test wrote a random one — it passed only because
    // the suite ran with foreign keys disabled, and it was the single failure
    // when they were switched on. Written by me in Story 4; found by the
    // enforcement this epic enabled, which is the point of enabling it.
    $mediaId = idnMediaAsset();
    [$contestantId] = idnContestant(['photo_media_id' => $mediaId]);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // BEFORE: the id travelled and there was nothing a screen could do with
    //         it — no url, no thumbnail, no mime type.
    // AFTER:  it still travels, and `photo` resolves beside it. The id was
    //         not replaced, because a consumer already reads it.
    expect($response->json('data.contestant.photo_media_asset_id'))->toBe($mediaId);
});

test('GM [CHANGED IN STORY 4]: an age is now reported', function (): void {
    [$contestantId] = idnContestant(['date_of_birth' => '2000-01-01']);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // BEFORE: BirthDate::calculateAgeAt() had existed since the module was
    //         written and no endpoint had ever called it.
    // AFTER:  the identity endpoint does, and only that one (D24).
    expect($response->json('data.contestant.age'))->toBeInt();
});

test('GM: missing_fields already travels, and holds only the photo', function (): void {
    [$contestantId] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // Recorded because it is the evidence behind D24's warning about the
    // metric. full_name, phone_number and country_id are required at
    // creation and cannot be blanked through the API, and date_of_birth is
    // counted unconditionally — so the photo is the only field that can be
    // missing, and the score is 80 or 100 and nothing else.
    expect($response->json('data.contestant.profile_completeness.missing_fields'))
        ->toBe(['photo_media_asset_id']);
    expect($response->json('data.contestant.profile_completeness.completeness_percent'))->toBe(80);
});

test('the user branch carries exactly the four fields D18 admits', function (): void {
    [$contestantId, $account] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect(array_keys($response->json('data.user')))
        ->toEqualCanonicalizing(['id', 'name', 'status', 'type']);

    expect($response->json('data.user.id'))->toBe((string) $account->id);
    expect($response->json('data.user.name'))->toBe('Account Name');
    expect($response->json('data.user.type'))->toBe('contestant');
});

test('the account email never crosses the link — D18', function (): void {
    $account = idnUser(UserType::CONTESTANT, ['email' => 'private-address@quran.test']);
    [$contestantId] = idnContestant([], $account);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // Asserted against the whole body, not just the user branch: the point is
    // that the address is nowhere in the response, however it got there.
    expect($response->getContent())->not->toContain('private-address@quran.test');
});

test('no user_profiles field appears anywhere, even when a profile exists', function (): void {
    $account = idnUser(UserType::CONTESTANT);
    [$contestantId] = idnContestant([], $account);

    DB::table('user_profiles')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => (string) $account->id,
        'display_name' => 'SELF CHOSEN NAME',
        'bio' => 'SELF WRITTEN BIO',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect($response->json('data'))->not->toHaveKey('profile');
    expect($response->getContent())->not->toContain('SELF CHOSEN NAME');
    expect($response->getContent())->not->toContain('SELF WRITTEN BIO');
});

test('national_id is absent from identity although show still carries it — D19', function (): void {
    [$contestantId] = idnContestant(['national_id' => '9998887776']);
    $admin = idnAs('super_admin');

    $identity = $this->actingAs($admin)
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect($identity->json('data.contestant'))->not->toHaveKey('national_id');
    expect($identity->getContent())->not->toContain('9998887776');

    // The same reader, one route over, still gets it. The field did not move
    // or become unreachable — it stayed where a deliberate act reaches it.
    $this->actingAs($admin)
        ->getJson("/api/v1/admin/contestants/{$contestantId}")
        ->assertOk()
        ->assertJsonPath('data.national_id', '9998887776');
});

/*
|--------------------------------------------------------------------------
| The photo and the age — Story 4 (ADR-016 D24)
|--------------------------------------------------------------------------
*/

/** A media asset the boundary can resolve. */
function idnMediaAsset(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('media_assets')->insert(array_merge([
        'id' => $id,
        'uploader_id' => null,
        'disk' => 'public',
        'file_path' => 'contestants/'.Str::random(8).'.jpg',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 4096,
        'hash_sha256' => hash('sha256', $id),
        'collection' => 'contestant_photos',
        'custom_properties' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

test('the photo resolves to something a screen can render', function (): void {
    $mediaId = idnMediaAsset([
        'custom_properties' => json_encode(['thumb_url' => 'https://cdn.test/thumb.jpg']),
    ]);
    [$contestantId] = idnContestant(['photo_media_id' => $mediaId]);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    $photo = $response->json('data.contestant.photo');

    expect($photo['id'])->toBe($mediaId);
    expect($photo['url'])->toBeString();
    expect($photo['thumb'])->toBe('https://cdn.test/thumb.jpg');
    expect($photo['is_image'])->toBeTrue();

    // The bare id stays. It was in the contract before Story 4 and removing
    // it would break a consumer to add a convenience.
    expect($response->json('data.contestant.photo_media_asset_id'))->toBe($mediaId);
});

test('a contestant with no photo reports null rather than an empty object', function (): void {
    [$contestantId] = idnContestant();

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.contestant.photo', null);
});

test('a photo id pointing at a deleted asset reports null, never a broken url', function (): void {
    $mediaId = idnMediaAsset();
    [$contestantId] = idnContestant(['photo_media_id' => $mediaId]);

    DB::table('media_assets')->where('id', $mediaId)->update(['deleted_at' => now()]);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // The id still travels — the record says a photo was set — but nothing
    // pretends there is a file behind it.
    expect($response->json('data.contestant.photo'))->toBeNull();
    expect($response->json('data.contestant.photo_media_asset_id'))->toBe($mediaId);
});

test('the photo needs no permission beyond contestants.view', function (string $roleName): void {
    $mediaId = idnMediaAsset();
    [$contestantId] = idnContestant(['photo_media_id' => $mediaId]);

    // D24: every seeded role holds media.view, so a withheld photo would be
    // an unreachable state dressed as a permission boundary. `withheld`
    // never names it.
    $response = $this->actingAs(idnAs($roleName))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect($response->json('data.contestant.photo.id'))->toBe($mediaId);
    expect($response->json('data.withheld'))->not->toContain('photo');
})->with(['super_admin', 'competition_manager', 'data_entry']);

test('resolving the photo does not add a query per request', function (): void {
    $mediaId = idnMediaAsset();
    [$withPhoto] = idnContestant(['photo_media_id' => $mediaId]);
    [$withoutPhoto] = idnContestant();
    $admin = idnAs('super_admin');

    $countQueries = function (string $contestantId) use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
            ->assertOk();

        $count = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // Warm the permission resolver first — an unwarmed call costs one extra
    // query and would read as a difference the photo caused.
    $countQueries($withoutPhoto);

    $none = $countQueries($withoutPhoto);
    $one = $countQueries($withPhoto);

    // Exactly one more: the batch that resolves the asset. Not two, and not
    // one per field read off it.
    expect($one)->toBe($none + 1);
});

test('the age is calculated by the domain and travels as a number', function (): void {
    [$contestantId] = idnContestant([
        'date_of_birth' => now()->subYears(24)->subDays(3)->format('Y-m-d'),
    ]);

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.contestant.age', 24);
});

test('the age counts completed years, not calendar ones', function (): void {
    // A birthday that has not happened yet this year. Someone born 20 years
    // ago minus one day is 19, and a naive year subtraction would say 20.
    [$contestantId] = idnContestant([
        'date_of_birth' => now()->subYears(20)->addDay()->format('Y-m-d'),
    ]);

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.contestant.age', 19);
});

test('GM [CHANGED IN STORY 4]: the contestant branch now carries twelve keys', function (): void {
    [$contestantId] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // BEFORE: ten. AFTER: twelve — `age` and `photo` (ADR-016 D24).
    // `photo_media_asset_id` deliberately stays alongside `photo`.
    expect(array_keys($response->json('data.contestant')))->toEqualCanonicalizing([
        'id',
        'user_id',
        'country_id',
        'full_name',
        'date_of_birth',
        'gender',
        'phone_number',
        'photo_media_asset_id',
        'photo',
        'age',
        'is_deleted',
        'profile_completeness',
    ]);
});

test('the record-editing contract is NOT widened — D24', function (): void {
    [$contestantId] = idnContestant();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}")
        ->assertOk();

    // GET /admin/contestants/{id} stays exactly as Story 1 shaped it. It is
    // what the edit form reads, and this story has no screen for a wider
    // version of it.
    expect($response->json('data'))->not->toHaveKey('age');
    expect($response->json('data'))->not->toHaveKey('photo');
    expect($response->json('data.national_id'))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The account status — the fact D18 calls the most operationally useful
|--------------------------------------------------------------------------
*/

test('the account status is derived, not guessed', function (array $columns, string $expected): void {
    $account = idnUser(UserType::CONTESTANT, $columns);
    [$contestantId] = idnContestant([], $account);

    if ($expected === 'deleted') {
        UserModel::query()->whereKey($account->id)->delete();
    }

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.user.status', $expected);
})->with([
    'active' => [['is_active' => true], 'active'],
    'deactivated' => [['is_active' => false], 'deactivated'],
    'pending' => [['password_hash' => null], 'pending_activation'],
    'deleted' => [['is_active' => true], 'deleted'],
]);

test('a deleted account still resolves, reported as deleted rather than missing', function (): void {
    $account = idnUser(UserType::CONTESTANT);
    [$contestantId] = idnContestant([], $account);

    UserModel::query()->whereKey($account->id)->delete();

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // Not null. "An account that can no longer sign in" is a different and
    // more useful fact than "no account", and it is the reason D18 admits
    // status at all.
    expect($response->json('data.user'))->not->toBeNull();
    expect($response->json('data.user.status'))->toBe('deleted');
});

/*
|--------------------------------------------------------------------------
| Country
|--------------------------------------------------------------------------
*/

test('the country name follows the requested language', function (string $header, string $expected): void {
    [$contestantId] = idnContestant();

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity", ['Accept-Language' => $header])
        ->assertOk()
        ->assertJsonPath('data.country.name', $expected);
})->with([
    'arabic' => ['ar', 'الأردن'],
    'english' => ['en', 'Jordan'],
    'spanish' => ['es', 'Jordania'],
]);

test('a country with no translation for the requested language falls back rather than blanking', function (): void {
    $countryId = idnCountry(['ar' => 'الأردن']);
    [$contestantId] = idnContestant(['country_id' => $countryId]);

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity", ['Accept-Language' => 'es'])
        ->assertOk()
        ->assertJsonPath('data.country.name', 'الأردن');
});

test('a country with no translations at all falls back to its ISO code', function (): void {
    $iso2 = null;
    $countryId = idnCountry([], $iso2);
    [$contestantId] = idnContestant(['country_id' => $countryId]);

    // Compared against what the helper actually stored. Hardcoding a code
    // here would tie the assertion to a value the helper stopped promising
    // once it had to generate unique ones.
    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.country.name', $iso2);
});

/*
|--------------------------------------------------------------------------
| Memberships — the branch, and D20's distinction
|--------------------------------------------------------------------------
*/

test('the whole circle history travels, newest first, with its centre', function (): void {
    $countryId = idnCountry();
    [$contestantId] = idnContestant(['country_id' => $countryId]);

    [, $firstCircle] = idnCircle($countryId, 'Old Centre', 'Old Circle');
    [, $secondCircle] = idnCircle($countryId, 'New Centre', 'New Circle');

    idnMembership($contestantId, $firstCircle, joinedAt: now()->subYears(2)->toDateTimeString(), leftAt: now()->subYear()->toDateTimeString(), reason: 'Moved city');
    idnMembership($contestantId, $secondCircle, joinedAt: now()->subYear()->toDateTimeString());

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    $memberships = $response->json('data.memberships');

    // Both periods, not just the open one. Q4 rejected contestants.circle_id
    // precisely because it keeps the present and loses every transfer.
    expect($memberships)->toHaveCount(2);

    expect($memberships[0]['circle_name'])->toBe('New Circle');
    expect($memberships[0]['center_name'])->toBe('New Centre');
    expect($memberships[0]['center_city'])->toBe('Amman');
    expect($memberships[0]['is_active'])->toBeTrue();
    expect($memberships[0]['left_at'])->toBeNull();

    expect($memberships[1]['circle_name'])->toBe('Old Circle');
    expect($memberships[1]['is_active'])->toBeFalse();
    expect($memberships[1]['reason'])->toBe('Moved city');
});

test('withheld names the branch a reader may not see — D20', function (): void {
    $countryId = idnCountry();
    [$contestantId] = idnContestant(['country_id' => $countryId]);
    [, $circleId] = idnCircle($countryId);
    idnMembership($contestantId, $circleId);

    // data_entry holds contestants.view and not memberships.view.
    $response = $this->actingAs(idnAs('data_entry'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect($response->json('data.memberships'))->toBe([]);
    expect($response->json('data.withheld'))->toBe(['memberships']);
});

test('an empty history and a withheld one are distinguishable', function (): void {
    [$contestantId] = idnContestant();

    // Same contestant, no memberships at all, read by somebody who may see
    // them. This is the pair that makes `withheld` worth its key: without it
    // this response and the one above are byte-identical in `memberships`,
    // and the panel would say "never belonged to a circle" to a reader who
    // simply is not allowed to know.
    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    expect($response->json('data.memberships'))->toBe([]);
    expect($response->json('data.withheld'))->toBe([]);
});

test('competition_manager also has the memberships branch withheld', function (): void {
    $countryId = idnCountry();
    [$contestantId] = idnContestant(['country_id' => $countryId]);
    [, $circleId] = idnCircle($countryId);
    idnMembership($contestantId, $circleId);

    $this->actingAs(idnAs('competition_manager'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk()
        ->assertJsonPath('data.withheld', ['memberships']);
});

test('a deleted circle leaves the membership readable with a null circle name', function (): void {
    $countryId = idnCountry();
    [$contestantId] = idnContestant(['country_id' => $countryId]);
    [, $circleId] = idnCircle($countryId);
    idnMembership($contestantId, $circleId);

    DB::table('circles')->where('id', $circleId)->update(['deleted_at' => now()]);

    $response = $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertOk();

    // The membership happened; the circle it happened in is gone. Dropping
    // the row instead would erase a period of a person's history because a
    // circle was tidied up.
    expect($response->json('data.memberships'))->toHaveCount(1);
    expect($response->json('data.memberships.0.circle_id'))->toBe($circleId);
    expect($response->json('data.memberships.0.circle_name'))->toBeNull();
    expect($response->json('data.memberships.0.center_name'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Failure modes
|--------------------------------------------------------------------------
*/

test('a malformed id is 422 with INVALID_CONTESTANT_ID', function (): void {
    $this->actingAs(idnAs('super_admin'))
        ->getJson('/api/v1/admin/contestants/not-a-uuid/identity')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_CONTESTANT_ID');
});

test('a well-formed id that does not exist is 404', function (): void {
    $this->actingAs(idnAs('super_admin'))
        ->getJson('/api/v1/admin/contestants/'.Str::uuid().'/identity')
        ->assertStatus(404);
});

test('a soft-deleted contestant is not reachable, matching show', function (): void {
    [$contestantId] = idnContestant();
    DB::table('contestants')->where('id', $contestantId)->update(['deleted_at' => now()]);

    $this->actingAs(idnAs('super_admin'))
        ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
        ->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| Performance — the N+1 this endpoint is most likely to grow
|--------------------------------------------------------------------------
*/

test('the query count does not grow with the number of memberships', function (): void {
    $countryId = idnCountry();
    [$contestantId] = idnContestant(['country_id' => $countryId]);
    $admin = idnAs('super_admin');

    [, $firstCircle] = idnCircle($countryId);
    idnMembership($contestantId, $firstCircle, leftAt: now()->subMonth()->toDateTimeString());

    $countQueries = function () use ($admin, $contestantId): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/contestants/{$contestantId}/identity")
            ->assertOk();

        $count = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // Warm first, measure second. The effective-permission resolver caches
    // per user, so an unwarmed first call costs one extra query and would
    // read as an N+1 improvement when the second measurement is taken.
    $countQueries();

    $withOne = $countQueries();

    // Six more circles, each in its own centre — the worst case for the
    // nested load, since no two memberships share a circle or a centre.
    foreach (range(1, 6) as $n) {
        [, $circleId] = idnCircle($countryId, "Centre {$n}", "Circle {$n}");
        idnMembership($contestantId, $circleId, leftAt: now()->subDays($n)->toDateTimeString());
    }

    $withSeven = $countQueries();

    expect($withSeven)->toBe($withOne);
});
