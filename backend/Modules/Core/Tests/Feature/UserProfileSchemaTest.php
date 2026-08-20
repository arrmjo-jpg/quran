<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'profiles');

/**
 * Foreign keys are OFF for the suite as a whole.
 *
 * `tests/bootstrap-testing-env.php` sets `DB_FOREIGN_KEYS=false` against
 * SQLite `:memory:`, so no referential action in this platform — CASCADE,
 * SET NULL or RESTRICT — is exercised by any test. That is a gap worth its own
 * change; what it must not do is make a test here assert something the engine
 * was told not to enforce, and then read as if the schema had been proved.
 *
 * So this file turns them on for itself. If the pragma cannot be honoured the
 * two referential tests skip rather than pass, because a green test that never
 * checked anything is worse than an absent one.
 */
beforeEach(function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    }
});

function referentialActionsEnforced(): bool
{
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return true;
    }

    return (int) (DB::select('PRAGMA foreign_keys')[0]->foreign_keys ?? 0) === 1;
}

/*
|--------------------------------------------------------------------------
| user_profiles — the constraints, not the columns
|--------------------------------------------------------------------------
|
| Story 1 of Epic 3 ships a table and nothing else: no domain entity, no use
| case, no endpoint. What is worth testing at that point is not that the
| columns exist — the migration would have failed — but that the rules
| expressed in the schema actually fire. Each of these fails if its constraint
| is removed, which is the only reason to write them now rather than later.
*/

function profileUser(string $email = 'profile-owner@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Profile Owner',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function insertProfile(string $userId, ?string $avatarId = null): string
{
    $id = (string) Str::uuid();

    DB::table('user_profiles')->insert([
        'id' => $id,
        'user_id' => $userId,
        'display_name' => 'Displayed Name',
        'bio' => 'A short biography.',
        'avatar_media_id' => $avatarId,
        'social_links' => json_encode(['website' => 'https://example.test']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function profileMediaAsset(): string
{
    $id = (string) Str::uuid();

    DB::table('media_assets')->insert([
        'id' => $id,
        'disk' => 'public',
        'file_path' => 'avatars/test.png',
        'file_name' => 'test.png',
        'mime_type' => 'image/png',
        'size_bytes' => 1024,
        'collection' => 'avatars',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

test('an account may hold only one profile', function (): void {
    // Enforced by the database rather than by whichever use case writes next:
    // two concurrent first-time saves would both find no row and both insert.
    $user = profileUser();
    insertProfile((string) $user->id);

    expect(fn () => insertProfile((string) $user->id))
        ->toThrow(QueryException::class);
});

test('soft deleting an account leaves its profile alone', function (): void {
    // The normal path. Accounts are soft deleted under ADR-005, so the CASCADE
    // below must not fire here — a deactivated administrator who is restored
    // should not come back without a biography.
    $user = profileUser();
    $profileId = insertProfile((string) $user->id);

    $user->delete();

    expect(UserModel::query()->find($user->id))->toBeNull()
        ->and(DB::table('user_profiles')->where('id', $profileId)->exists())->toBeTrue();
});

test('hard deleting an account takes its profile with it', function (): void {
    // The exceptional path, and the reason this FK is CASCADE while most user
    // references in the schema are SET NULL: those columns record that someone
    // did something and outlive them, whereas this row IS the account's
    // profile and means nothing without it.
    $user = profileUser();
    $profileId = insertProfile((string) $user->id);

    $user->forceDelete();

    expect(DB::table('user_profiles')->where('id', $profileId)->exists())->toBeFalse();
})->skip(fn (): bool => ! referentialActionsEnforced(), 'Foreign keys are disabled for this suite.');

test('deleting the avatar asset clears the field rather than blocking', function (): void {
    // SET NULL, following contestants.photo_media_id rather than
    // PlatformBlueprint::mediaForeign's RESTRICT. Losing the picture is the
    // right outcome; refusing to delete a media asset because someone used it
    // as an avatar is not.
    $user = profileUser();
    $mediaId = profileMediaAsset();
    $profileId = insertProfile((string) $user->id, $mediaId);

    DB::table('media_assets')->where('id', $mediaId)->delete();

    $profile = DB::table('user_profiles')->where('id', $profileId)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->avatar_media_id)->toBeNull();
})->skip(fn (): bool => ! referentialActionsEnforced(), 'Foreign keys are disabled for this suite.');

test('social_links round-trips as JSON and omits platforms that are unset', function (): void {
    // Q5: the column carries the links that exist, not a fixed shape with
    // eight slots and five nulls.
    $user = profileUser();
    $profileId = insertProfile((string) $user->id);

    $stored = json_decode(
        (string) DB::table('user_profiles')->where('id', $profileId)->value('social_links'),
        true
    );

    expect($stored)->toBe(['website' => 'https://example.test'])
        ->and($stored)->not->toHaveKey('facebook');
});
