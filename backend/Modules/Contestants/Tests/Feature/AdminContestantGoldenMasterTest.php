<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('contestants', 'feature', 'golden-master');

/*
|--------------------------------------------------------------------------
| GET /admin/contestants and /{id}, exactly as they behave before Story 1
|--------------------------------------------------------------------------
|
| Epic 4 Story 1 gives contestants create, update, delete and restore, and
| the list a bound. Both endpoints below change as a result: the list gains
| pagination it does not have, and the resource may gain fields.
|
| Nothing here asserts that the current behaviour is GOOD. Several of these
| pin things Discovery recorded as defects — the unbounded list, the two
| resources that disagree about which fields exist, the search that scans
| `national_id` with a leading wildcard. They exist so that the change which
| follows has to declare what it alters instead of altering it quietly.
|
| Written before the work, in the pattern Epic 2 used for
| SubmitApplicationUseCase and Epic 3 for SelfProfileGoldenMasterTest.
*/

function gmAdmin(): UserModel
{
    $admin = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'gm-contestants-admin@quran.test',
        'name' => 'Golden Master Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    grantSuperAdmin((string) $admin->id);

    return $admin->fresh();
}

function gmCountry(): string
{
    $existing = DB::table('countries')->value('id');

    if ($existing !== null) {
        return (string) $existing;
    }

    $id = (string) Str::uuid();

    DB::table('countries')->insert([
        'id' => $id,
        'iso_code' => 'JO',
        'iso3_code' => 'JOR',
        'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * A contestant, written straight to the table.
 *
 * Deliberately not through an endpoint: there is no create route, which is
 * the gap Story 1 fills and the reason this file exists.
 */
function gmContestant(array $overrides = []): string
{
    $id = $overrides['id'] ?? (string) Str::uuid();

    $userId = (string) Str::uuid();
    UserModel::query()->create([
        'id' => $userId,
        'email' => 'gm-contestant-'.Str::random(8).'@quran.test',
        'name' => 'Account Name',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    DB::table('contestants')->insert(array_merge([
        'id' => $id,
        'user_id' => $userId,
        'country_id' => gmCountry(),
        'full_name' => 'Khalid Al-Fahad',
        'date_of_birth' => '1998-06-15',
        'gender' => 'male',
        'national_id' => '9981234567',
        'phone_number' => '+962791234567',
        'photo_media_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], array_diff_key($overrides, ['id' => null])));

    return $id;
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('GM: both endpoints require authentication', function (): void {
    $this->getJson('/api/v1/admin/contestants')->assertStatus(401);
    $this->getJson('/api/v1/admin/contestants/'.Str::uuid())->assertStatus(401);
});

test('GM: both endpoints refuse an admin without contestants.view', function (): void {
    $admin = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'gm-no-perm@quran.test',
        'name' => 'No Permission',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->assertStatus(403);
    $this->actingAs($admin)->getJson('/api/v1/admin/contestants/'.Str::uuid())->assertStatus(403);
});

test('GM: a contestant-typed account is refused the admin surface', function (): void {
    $contestant = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'gm-contestant-user@quran.test',
        'name' => 'A Contestant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $this->actingAs($contestant)->getJson('/api/v1/admin/contestants')->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| GET /admin/contestants
|--------------------------------------------------------------------------
*/

test('GM [CHANGED IN STORY 1]: the list is paginated and carries meta.pagination', function (): void {
    // BEFORE: success + data, no meta, every contestant on the platform.
    // AFTER:  success + data + meta.pagination, 20 per page by default.
    //
    // The change the Golden Master existed to make visible. Story 1 could
    // not ship an unbounded list — it computes profile_completeness per row
    // and returned every national ID the platform held in one response.
    // Shape follows UserController and CenterController exactly.
    gmContestant();

    $response = $this->actingAs(gmAdmin())->getJson('/api/v1/admin/contestants');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 20)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.pagination.last_page', 1);

    expect($response->json())->toHaveKeys(['success', 'data', 'meta']);
});

test('GM [CHANGED IN STORY 1]: the list drops national_id and gains is_deleted', function (): void {
    // BEFORE: ten keys, national_id among them.
    // AFTER:  ten keys, national_id gone, is_deleted added.
    //
    // The list is a browsing surface. Before Story 1 one request returned
    // every contestant with their identity document in full; paginating
    // reduces that per request but not in principle, since paging through
    // still collects them all. No admin list column displays it. The detail
    // endpoint keeps it — see the test below — because that is one person
    // looked at deliberately.
    gmContestant();

    $row = $this->actingAs(gmAdmin())->getJson('/api/v1/admin/contestants')->json('data.0');

    expect(array_keys($row))->toBe([
        'id',
        'user_id',
        'country_id',
        'full_name',
        'date_of_birth',
        'gender',
        'phone_number',
        'photo_media_asset_id',
        'is_deleted',
        'profile_completeness',
    ]);
});

test('GM [CHANGED IN STORY 1]: list and show now differ by exactly national_id', function (): void {
    // BEFORE: identical key sets — both took ContestantPrivateResource's
    //         entity branch, and the model branch was unreachable.
    // AFTER:  the list has its own resource. The ONLY difference is
    //         national_id, asserted here rather than described, so a future
    //         field cannot diverge between the two unnoticed.
    $id = gmContestant();
    $admin = gmAdmin();

    $listRow = $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json('data.0');
    $showRow = $this->actingAs($admin)->getJson("/api/v1/admin/contestants/{$id}")->json('data');

    expect(array_diff(array_keys($showRow), array_keys($listRow)))->toBe([7 => 'national_id']);
    expect(array_diff(array_keys($listRow), array_keys($showRow)))->toBe([]);
    expect($listRow)->toHaveKey('profile_completeness');
});

test('GM: the list is ordered by full_name ascending', function (): void {
    gmContestant(['full_name' => 'Zayd Al-Ansari']);
    gmContestant(['full_name' => 'Bilal Al-Habashi']);
    gmContestant(['full_name' => 'Anas Al-Madani']);

    $names = $this->actingAs(gmAdmin())
        ->getJson('/api/v1/admin/contestants')
        ->json('data.*.full_name');

    expect($names)->toBe(['Anas Al-Madani', 'Bilal Al-Habashi', 'Zayd Al-Ansari']);
});

test('GM [CHANGED IN STORY 1]: ?q searches full_name and phone_number, NOT national_id', function (): void {
    // BEFORE: full_name, phone_number and national_id.
    // AFTER:  national_id is not searchable.
    //
    // A leading-wildcard LIKE over an identity document lets any holder of
    // contestants.view confirm or enumerate fragments of one — a lookup
    // oracle, not a search feature — and the subjects may be minors. That is
    // the reasoning D11 uses to withhold contestant visibility from
    // supervisors until scoping is real, applied to a field rather than a
    // role. Nothing in the admin UI searched by it.
    gmContestant(['full_name' => 'Searchable Person', 'phone_number' => '+962700000001', 'national_id' => '1110000001']);
    gmContestant(['full_name' => 'Other Person', 'phone_number' => '+962700000002', 'national_id' => '2220000002']);

    $admin = gmAdmin();

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=Searchable')->json('data'))->toHaveCount(1);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=0000001')->json('data'))->toHaveCount(1);

    // The national ID of a real contestant now matches nothing.
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=1110000001')->json('data'))->toHaveCount(0);
});

test('the list accepts ?search as well as ?q, with identical results', function (): void {
    // `q` is what this endpoint has always taken and keeps taking; `search`
    // is what every other admin list uses. Two spellings of one input, not
    // two contracts.
    gmContestant(['full_name' => 'Searchable Person']);
    gmContestant(['full_name' => 'Other Person']);

    $admin = gmAdmin();

    $byQ = $this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=Searchable')->json('data');
    $bySearch = $this->actingAs($admin)->getJson('/api/v1/admin/contestants?search=Searchable')->json('data');

    expect($byQ)->toHaveCount(1);
    expect($bySearch)->toBe($byQ);
});

test('GM: ?q matches on a substring, anywhere in the value', function (): void {
    // A leading-wildcard LIKE. Pinned because Story 1 may replace it, and
    // an exact-match or prefix search would silently change results for
    // anyone relying on this.
    gmContestant(['full_name' => 'Abdurrahman ibn Awf']);

    $found = $this->actingAs(gmAdmin())->getJson('/api/v1/admin/contestants?q=rahman')->json('data');

    expect($found)->toHaveCount(1);
});

test('GM: an empty ?q returns everything, not nothing', function (): void {
    gmContestant();
    gmContestant();

    $admin = gmAdmin();

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=')->json('data'))->toHaveCount(2);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json('data'))->toHaveCount(2);
});

test('GM: a query matching nothing returns an empty data array, not 404', function (): void {
    gmContestant();

    $response = $this->actingAs(gmAdmin())->getJson('/api/v1/admin/contestants?q=nobody-by-this-name');

    $response->assertStatus(200)->assertJsonPath('success', true);
    expect($response->json('data'))->toBe([]);
});

test('GM: soft-deleted contestants are absent from the list', function (): void {
    $id = gmContestant();
    DB::table('contestants')->where('id', $id)->update(['deleted_at' => now()]);

    expect($this->actingAs(gmAdmin())->getJson('/api/v1/admin/contestants')->json('data'))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| GET /admin/contestants/{id}
|--------------------------------------------------------------------------
*/

test('GM [CHANGED IN STORY 1]: show keeps national_id and gains is_deleted', function (): void {
    // BEFORE: ten keys.
    // AFTER:  eleven — is_deleted added so the panel can tell a restored
    //         record from a live one. national_id stays: an administrator
    //         opening one contestant is looking at one person on purpose.
    $id = gmContestant();

    $data = $this->actingAs(gmAdmin())->getJson("/api/v1/admin/contestants/{$id}")->json('data');

    expect(array_keys($data))->toBe([
        'id',
        'user_id',
        'country_id',
        'full_name',
        'date_of_birth',
        'gender',
        'phone_number',
        'national_id',
        'photo_media_asset_id',
        'is_deleted',
        'profile_completeness',
    ]);
});

test('GM: profile_completeness is 80% with no photo, and names the missing field', function (): void {
    // Five weighted facts, one of which (date_of_birth) is counted as
    // present unconditionally because the aggregate cannot be built
    // without it. A contestant with everything but a photo scores 4/5.
    $id = gmContestant(['photo_media_id' => null]);

    $completeness = $this->actingAs(gmAdmin())
        ->getJson("/api/v1/admin/contestants/{$id}")
        ->json('data.profile_completeness');

    expect($completeness)->toBe([
        'completeness_percent' => 80,
        'is_complete' => false,
        'missing_fields' => ['photo_media_asset_id'],
    ]);
});

test('GM: date_of_birth is returned as Y-m-d and gender as its raw string', function (): void {
    $id = gmContestant(['date_of_birth' => '1998-06-15', 'gender' => 'male']);

    $data = $this->actingAs(gmAdmin())->getJson("/api/v1/admin/contestants/{$id}")->json('data');

    expect($data['date_of_birth'])->toBe('1998-06-15');
    expect($data['gender'])->toBe('male');
});

test('GM: show carries user_id, the only link to the account that exists today', function (): void {
    // The one thread Epic 4's Identity 360 will pull on. Pinned so Story 2
    // cannot change its name or drop it without saying so.
    $id = gmContestant();

    $userId = DB::table('contestants')->where('id', $id)->value('user_id');

    expect($this->actingAs(gmAdmin())->getJson("/api/v1/admin/contestants/{$id}")->json('data.user_id'))
        ->toBe($userId);
});

test('GM: a malformed id returns 422 with INVALID_CONTESTANT_ID', function (): void {
    $this->actingAs(gmAdmin())
        ->getJson('/api/v1/admin/contestants/not-a-uuid')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_CONTESTANT_ID');
});

test('GM: a well-formed id that does not exist returns 404 NOT_FOUND', function (): void {
    // Different from the malformed case above, and the difference is
    // load-bearing: 422 says "that is not an id", 404 says "no such
    // contestant". Story 1 must keep both.
    $this->actingAs(gmAdmin())
        ->getJson('/api/v1/admin/contestants/'.Str::uuid())
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('GM: a soft-deleted contestant is not retrievable by id', function (): void {
    $id = gmContestant();
    DB::table('contestants')->where('id', $id)->update(['deleted_at' => now()]);

    $this->actingAs(gmAdmin())->getJson("/api/v1/admin/contestants/{$id}")->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| What does NOT exist — pinned so Story 1's additions are visible
|--------------------------------------------------------------------------
*/

test('GM [CHANGED IN STORY 1]: create, update, delete and restore now exist', function (): void {
    // BEFORE: 405 on every write verb, 404 on restore. The gap D15 named.
    // AFTER:  all four routed. Behaviour is covered in
    //         AdminContestantManagementTest; this only records that they
    //         stopped being absent.
    $admin = gmAdmin();
    $id = gmContestant();

    // 422 rather than 405: the route exists and validation rejects an
    // empty body.
    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', [])->assertStatus(422);

    // An empty PATCH is a valid no-op update.
    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", [])->assertStatus(200);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);
    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")->assertStatus(200);

    // Still there — deletion was soft, per ADR-016 D5.
    expect(DB::table('contestants')->where('id', $id)->exists())->toBeTrue();
});

test('GM: no membership, circle or centre data crosses onto a contestant', function (): void {
    // Epic 4 Story 2 adds this. Pinned so its arrival is a visible change
    // to this file rather than an unremarked widening of the contract.
    $id = gmContestant();
    enrolInCircle($id);

    $data = $this->actingAs(gmAdmin())->getJson("/api/v1/admin/contestants/{$id}")->json('data');

    expect($data)->not->toHaveKey('memberships');
    expect($data)->not->toHaveKey('circle');
    expect($data)->not->toHaveKey('center');
});
