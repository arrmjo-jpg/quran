<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Core\Domain\Events\UserProfileUpdated;
use Modules\Core\Domain\ValueObjects\SocialLinks;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'profiles');

/*
|--------------------------------------------------------------------------
| The profile on /me — Epic 3, Story 3
|--------------------------------------------------------------------------
|
| ADR-016 D1, D2, D13. Written before the implementation, so each of these was
| red for the reason it names rather than green for a reason nobody checked.
|
| Three decisions the board settled before any of this was written, and every
| test here depends on one of them:
|
|   The media reference is the raw id, under the column's own name —
|   `avatar_media_id` — and not a resolved URL. Core does not learn to
|   resolve media.
|
|   The profile fields nest under `profile`, so `user_profiles` reads as one
|   thing rather than as three loose keys mixed into the account.
|
|   Nothing here WRITES an avatar. Setting one needs an upload, and uploading
|   needs `media.create`, which self-service does not have. That question is
|   still open, so this story reads the column and adds no route.
*/

function profileTestUser(array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'profile-user@quran.test',
        'name' => 'Account Name',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'preferred_locale' => 'ar',
    ], $overrides));
}

function seedProfileRow(string $userId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('user_profiles')->insert(array_merge([
        'id' => $id,
        'user_id' => $userId,
        'display_name' => 'Seeded Display Name',
        'bio' => 'Seeded biography.',
        'avatar_media_id' => null,
        'social_links' => json_encode(['website' => 'https://seeded.test']),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

function profileMedia(): string
{
    $id = (string) Str::uuid();

    DB::table('media_assets')->insert([
        'id' => $id,
        'disk' => 'public',
        'file_path' => 'avatars/face.png',
        'file_name' => 'face.png',
        'mime_type' => 'image/png',
        'size_bytes' => 2048,
        'collection' => 'avatars',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/*
|--------------------------------------------------------------------------
| Reading
|--------------------------------------------------------------------------
*/

test('GET /me carries a profile block', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $profile = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data.profile');

    expect($profile)->toBeArray()
        ->and(array_keys($profile))->toBe([
            'display_name', 'bio', 'social_links', 'avatar_media_id',
        ]);
});

test('the profile block is read from user_profiles, not invented', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', 'Seeded Display Name')
        ->assertJsonPath('data.profile.bio', 'Seeded biography.')
        ->assertJsonPath('data.profile.social_links', ['website' => 'https://seeded.test']);
});

test('an account with no profile row still answers, with nulls', function (): void {
    // The row is created on first write, not when the account is. ADR-016
    // records the table as additive: no existing account data moves into it.
    $user = profileTestUser();

    $profile = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data.profile');

    // Read as an array first. `assertJsonPath('data.profile.bio', null)` alone
    // passes just as happily when `profile` is absent entirely, which would
    // make this test green before a line of the feature existed.
    expect($profile)->toBeArray()
        ->and($profile)->toHaveKeys(['display_name', 'bio', 'social_links', 'avatar_media_id'])
        ->and($profile['display_name'])->toBeNull()
        ->and($profile['bio'])->toBeNull()
        ->and($profile['social_links'])->toBeNull()
        ->and($profile['avatar_media_id'])->toBeNull();
});

test('display_name does not fall back to the account name', function (): void {
    // They are different fields answering different questions. A profile with
    // no display name has none; borrowing `users.name` would make the two
    // indistinguishable and hide whether anyone ever set one.
    $user = profileTestUser(['name' => 'Account Name']);
    seedProfileRow((string) $user->id, ['display_name' => null]);

    $data = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data');

    expect($data['name'])->toBe('Account Name')
        ->and($data['profile'])->toBeArray()
        ->and($data['profile'])->toHaveKey('display_name')
        ->and($data['profile']['display_name'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The avatar is read, and read from exactly one place
|--------------------------------------------------------------------------
*/

test('avatar_media_id comes from user_profiles', function (): void {
    $user = profileTestUser();
    $mediaId = profileMedia();
    seedProfileRow((string) $user->id, ['avatar_media_id' => $mediaId]);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.avatar_media_id', $mediaId);
});

test('changing the column changes the response', function (): void {
    // Proves the source positively rather than by absence: the value moves
    // when that column moves, so nothing else can be supplying it.
    $user = profileTestUser();
    $first = profileMedia();
    seedProfileRow((string) $user->id, ['avatar_media_id' => $first]);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.avatar_media_id', $first);

    $second = profileMedia();
    DB::table('user_profiles')->where('user_id', $user->id)->update(['avatar_media_id' => $second]);

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.avatar_media_id', $second);
});

test('removing the profile row takes the whole block back to null', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id, ['avatar_media_id' => profileMedia()]);

    DB::table('user_profiles')->where('user_id', $user->id)->delete();

    $profile = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data.profile');

    expect($profile)->toBeArray()
        ->and($profile)->toHaveKeys(['display_name', 'avatar_media_id'])
        ->and($profile['avatar_media_id'])->toBeNull()
        ->and($profile['display_name'])->toBeNull();
});

test('no route exists for uploading or setting an avatar in this story', function (): void {
    // Setting one needs an upload, and `POST /admin/media` requires
    // media.create which self-service does not carry. Rather than invent an
    // exemption, this story reads the column and stops.
    $user = profileTestUser();

    $this->actingAs($user)->postJson('/api/v1/me/avatar', [])->assertNotFound();

    expect(collect(Route::getRoutes())->contains(
        fn ($route): bool => str_contains($route->uri(), 'me/avatar')
    ))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The admin surface writes where it reads
|--------------------------------------------------------------------------
|
| frontend-admin reads from `/admin/auth/me`, and until Story 4 the only way
| to write was `/me` — a different prefix for the same account's own data.
| Both now exist and are the same controller method, so the panel never has to
| leave the `/admin` space to edit the person using it.
*/

test('PATCH /admin/auth/me edits the profile exactly as PATCH /me does', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/admin/auth/me', [
        'profile' => [
            'display_name' => 'Through The Admin Prefix',
            'bio' => 'Written on the admin surface.',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', 'Through The Admin Prefix')
        ->assertJsonPath('data.profile.bio', 'Written on the admin surface.');
});

test('PATCH /admin/auth/me refuses a bad link like its twin', function (): void {
    // Same request class, same rule, same value object — asserted rather than
    // assumed, because "points at the same method" is a claim about wiring
    // that a route file can quietly stop being true.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/admin/auth/me', [
        'profile' => ['social_links' => ['linkedin' => 'https://facebook.com/someone']],
    ])->assertStatus(422);
});

test('PATCH /admin/auth/me requires authentication', function (): void {
    $this->patchJson('/api/v1/admin/auth/me', ['profile' => ['bio' => 'Nobody']])->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Writing
|--------------------------------------------------------------------------
*/

test('PATCH /me creates the profile row on first write', function (): void {
    $user = profileTestUser();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->exists())->toBeFalse();

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'A first biography.']])
        ->assertOk()
        ->assertJsonPath('data.profile.bio', 'A first biography.');

    expect(DB::table('user_profiles')->where('user_id', $user->id)->count())->toBe(1);
});

test('PATCH /me updates display_name, bio and social_links', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => [
            'display_name' => 'New Display Name',
            'bio' => 'A new biography.',
            'social_links' => ['x' => 'https://x.com/someone'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', 'New Display Name')
        ->assertJsonPath('data.profile.bio', 'A new biography.')
        ->assertJsonPath('data.profile.social_links', ['x' => 'https://x.com/someone']);
});

test('a partial write leaves the fields it did not mention alone', function (): void {
    // PATCH is partial for the profile exactly as it is for the account. This
    // is the assertion that fails if the implementation rebuilds the row from
    // the request instead of updating it.
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'Only the biography moved.']])->assertOk();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.bio', 'Only the biography moved.')
        ->assertJsonPath('data.profile.display_name', 'Seeded Display Name')
        ->assertJsonPath('data.profile.social_links', ['website' => 'https://seeded.test']);
});

test('editing the account and the profile in one request works', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'name' => 'Renamed Account',
        'profile' => ['bio' => 'And a biography.'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Account')
        ->assertJsonPath('data.profile.bio', 'And a biography.');
});

/*
|--------------------------------------------------------------------------
| social_links goes through the value object
|--------------------------------------------------------------------------
*/

test('a link on the wrong host is refused', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['linkedin' => 'https://facebook.com/someone']],
    ])->assertStatus(422);
});

test('an unknown platform is refused', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['github' => 'https://github.com/someone']],
    ])->assertStatus(422);
});

test('a twitter.com link survives unchanged', function (): void {
    // The value object validates and does not rewrite — ADR-016 Q5. Asserted
    // end to end here, not only in the unit test, because a helpful
    // normalisation added in a controller would pass that one.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['x' => 'https://twitter.com/someone']],
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.social_links', ['x' => 'https://twitter.com/someone']);
});

test('an unset platform carries no key', function (): void {
    $user = profileTestUser();

    $links = $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['website' => 'https://example.com']],
    ])->assertOk()->json('data.profile.social_links');

    expect($links)->toBe(['website' => 'https://example.com'])
        ->and($links)->not->toHaveKey('facebook');
});

test('a refused link leaves the stored profile untouched', function (): void {
    // The write must not half-apply: a bad link in the same request as a good
    // biography rejects both.
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => [
            'bio' => 'Should not be saved.',
            'social_links' => ['telegram' => 'https://example.com/someone'],
        ],
    ])->assertStatus(422);

    expect(DB::table('user_profiles')->where('user_id', $user->id)->value('bio'))
        ->toBe('Seeded biography.');
});

/*
|--------------------------------------------------------------------------
| The nested shape IS the contract
|--------------------------------------------------------------------------
*/

test('the flat shape is not the contract and writes nothing', function (): void {
    // GET returns the profile nested, so a panel that sends back what it read
    // must be the shape that works. The flat keys are simply unknown fields,
    // and unknown fields have always been dropped in silence here — which is
    // why this needs a test rather than a code comment: nothing else would
    // notice the endpoint answering 200 and saving none of it.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'display_name' => 'Flat Name',
        'bio' => 'Flat biography.',
    ])->assertOk();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('an empty social_links clears every link', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['social_links' => []]])
        ->assertOk();

    $links = $this->actingAs($user)->getJson('/api/v1/me')->json('data.profile.social_links');

    expect($links === null || $links === [])->toBeTrue();
});

test('omitting social_links is not the same as clearing it', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'Moved.']])->assertOk();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.social_links', ['website' => 'https://seeded.test']);
});

test('an explicit null clears a text field', function (): void {
    $user = profileTestUser();
    seedProfileRow((string) $user->id);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => null]])->assertOk();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.bio', null)
        ->assertJsonPath('data.profile.display_name', 'Seeded Display Name');
});

test('a null social_links is refused rather than given an invented meaning', function (): void {
    // An empty object clears and omission leaves alone, which covers both
    // intentions. A third spelling would have to mean one of them, and picking
    // which is a decision no specification has made — so it is refused until
    // one does.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['social_links' => null]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('writing twice does not leave a second profile row', function (): void {
    // uk_user_profiles_user_id would refuse the duplicate, but this suite does
    // not enforce constraints, so the count is asserted directly rather than
    // trusting a schema that is switched off here.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'First.']])->assertOk();
    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'Second.']])->assertOk();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('user_profiles')->where('user_id', $user->id)->value('bio'))->toBe('Second.');
});

test('a profile write leaves the account fields alone', function (): void {
    $user = profileTestUser(['preferred_locale' => 'en']);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'Only this.']])->assertOk();

    $fresh = UserModel::query()->find($user->id);

    expect($fresh->name)->toBe('Account Name')
        ->and($fresh->preferred_locale)->toBe('en');
});

/*
|--------------------------------------------------------------------------
| Every refusal is a 422, never a 500
|--------------------------------------------------------------------------
|
| SocialLinks throws InvalidArgumentException and bootstrap/app.php renders no
| handler for it, so anything reaching the value object unvalidated is a 500.
| That is the bug 59e2012 closed for Locale, where the HTTP rules and the value
| object disagreed. These are what stop it recurring on a second value object.
*/

test('an http website is refused, because website is https only', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['website' => 'http://example.com']],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a blank link is refused rather than read as unset', function (): void {
    // Q5: omit the key entirely; a key that is present must carry a link.
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['social_links' => ['x' => '']],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a display name longer than the column is refused', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['display_name' => str_repeat('a', 256)],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a biography longer than the column is refused', function (): void {
    $user = profileTestUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['bio' => str_repeat('a', 1001)],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('all eight platforms Q5 closed the set to are accepted', function (): void {
    $user = profileTestUser();

    $links = [
        'website' => 'https://someone.example',
        'x' => 'https://x.com/someone',
        'linkedin' => 'https://www.linkedin.com/in/someone',
        'facebook' => 'https://facebook.com/someone',
        'instagram' => 'https://instagram.com/someone',
        'youtube' => 'https://youtube.com/@someone',
        'telegram' => 'https://t.me/someone',
        'tiktok' => 'https://tiktok.com/@someone',
    ];

    // The set the endpoint accepts is the set the value object defines, in the
    // same order — so adding a platform to one and not the other fails here.
    expect(array_keys($links))->toBe(SocialLinks::platforms());

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['social_links' => $links]])
        ->assertOk()
        ->assertJsonPath('data.profile.social_links', $links);
});

test('avatar_media_id cannot be set through this endpoint', function (): void {
    $user = profileTestUser();
    $mine = profileMedia();
    seedProfileRow((string) $user->id, ['avatar_media_id' => $mine]);

    $another = profileMedia();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'profile' => ['avatar_media_id' => $another, 'bio' => 'Changed.'],
    ])->assertOk();

    $stored = DB::table('user_profiles')->where('user_id', $user->id)->first();

    expect($stored->avatar_media_id)->toBe($mine)
        ->and($stored->bio)->toBe('Changed.');
});

test('PINNED: editing only the profile records no event', function (): void {
    // UserProfileUpdated carries previousName/name — it is a name-change event
    // despite the broader name, and widening it to mean "something about this
    // person changed" would make every existing consumer read a different
    // thing. Profile history belongs to the Activity Log: ADR-016 D3, Q1 open.
    $user = profileTestUser();

    Event::fake([UserProfileUpdated::class]);

    $this->actingAs($user)->patchJson('/api/v1/me', ['profile' => ['bio' => 'Changed.']])->assertOk();

    Event::assertNotDispatched(UserProfileUpdated::class);
});

test('a name change still records its event when a profile write rides along', function (): void {
    $user = profileTestUser();

    Event::fake([UserProfileUpdated::class]);

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'name' => 'Renamed Account',
        'profile' => ['bio' => 'Changed.'],
    ])->assertOk();

    Event::assertDispatched(UserProfileUpdated::class);
});
