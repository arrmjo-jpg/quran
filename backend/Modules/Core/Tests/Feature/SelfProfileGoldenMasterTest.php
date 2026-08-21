<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Domain\Events\UserProfileUpdated;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'profiles', 'golden-master');

/*
|--------------------------------------------------------------------------
| GET /me and PATCH /me, exactly as they behave before Story 3
|--------------------------------------------------------------------------
|
| Story 3 moves this endpoint onto a use case and gives it the profile fields
| Story 1 created. Nothing here asserts that the current behaviour is good —
| several of these pin things that are wrong. They exist so that the change
| which follows has to declare what it alters instead of altering it quietly.
|
| Written before the refactor, in the pattern Epic 2 used for
| SubmitApplicationUseCase.
*/

function selfProfileUser(array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'self-profile@quran.test',
        'name' => 'Original Name',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'preferred_locale' => 'ar',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Reading
|--------------------------------------------------------------------------
*/

test('GET /me requires authentication', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('GET /me returns the account, and the shape Story 3 gave it', function (): void {
    // The list it has always returned, plus `profile` at the end. `avatar`
    // keeps its eighth place: adding a field is not licence to retire one.
    $user = selfProfileUser();

    $response = $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'name', 'email', 'type', 'status', 'is_active',
        'email_verified', 'avatar', 'roles', 'permissions', 'preferred_locale', 'profile',
    ]);
});

test('PINNED: avatar is still present and still null', function (): void {
    // `avatar` was hardcoded to null in both branches of UserResource since
    // before there was anywhere for an avatar to live, and nothing in
    // frontend-admin reads it. Story 3 adds profile.avatar_media_id beside it
    // and leaves it exactly where it was: retiring a published key is a
    // breaking change and belongs to a decision made out loud, not to a story
    // that happened to be editing the same resource.
    //
    // Checked with array_key_exists, NOT assertJsonPath. A path that does not
    // exist reads as null, so assertJsonPath('data.avatar', null) passes
    // whether the key is present-and-null or absent — it cannot fail in the
    // one direction it was written to catch, and it did not: the key was
    // removed at one point and this test went on passing.
    $user = selfProfileUser();

    $data = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data');

    expect(array_key_exists('avatar', $data))->toBeTrue()
        ->and($data['avatar'])->toBeNull()
        ->and($data['profile'])->toHaveKey('avatar_media_id');
});

test('profile fields are nested, not mixed into the account', function (): void {
    // One conceptual unit rather than three loose keys beside `name` and
    // `email`, so `user_profiles` reads as the thing it is.
    $user = selfProfileUser();

    $data = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data');

    expect($data)->not->toHaveKey('display_name')
        ->and($data)->not->toHaveKey('bio')
        ->and($data)->not->toHaveKey('social_links')
        ->and($data['profile'])->toHaveKeys(['display_name', 'bio', 'social_links']);
});

/*
|--------------------------------------------------------------------------
| Writing
|--------------------------------------------------------------------------
*/

test('PATCH /me updates the name', function (): void {
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    expect(UserModel::query()->find($user->id)->name)->toBe('Updated Name');
});

test('PATCH /me updates the preferred locale', function (): void {
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['preferred_locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.preferred_locale', 'en');
});

/*
| The two tests below were pinned as DEFECTS when this file was written, and
| the endpoint has since been corrected. They are kept, inverted, rather than
| deleted: a rule that was once wrong in a specific way is worth a test that
| names the way, or the same mismatch returns the next time someone edits the
| list without checking what the interface ships.
*/

test('/me accepts es, which the panel speaks', function (): void {
    // Was refused. An administrator could be created with es and an admin
    // could set another account to es, but that person could not choose it
    // for themselves — the endpoint disagreed with the two that write the
    // same column.
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['preferred_locale' => 'es'])
        ->assertOk()
        ->assertJsonPath('data.preferred_locale', 'es');
});

test('/me refuses fr, which the panel does not speak', function (): void {
    // Was accepted. `fr` has no translations anywhere in frontend-admin, so
    // storing it put an account into a language the interface cannot render.
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['preferred_locale' => 'fr'])
        ->assertStatus(422);

    expect(UserModel::query()->find($user->id)->preferred_locale)->toBe('ar');
});

/*
| PATCH is partial, and these two say what that means. Added before the
| refactor and passing on the original code, so that pointing the endpoint at
| UpdateUserProfileUseCase — whose signature wants both values — cannot quietly
| reset the field the caller left out. The admin path has exactly that bug:
| UserController::update passes `validated('locale', 'ar')`, so an
| administrator sending only a name resets that account to Arabic.
*/

test('sending only a name leaves the locale alone', function (): void {
    $user = selfProfileUser(['preferred_locale' => 'en']);

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated Name'])->assertOk();

    $fresh = UserModel::query()->find($user->id);

    expect($fresh->name)->toBe('Updated Name')
        ->and($fresh->preferred_locale)->toBe('en');
});

test('sending only a locale leaves the name alone', function (): void {
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['preferred_locale' => 'en'])->assertOk();

    $fresh = UserModel::query()->find($user->id);

    expect($fresh->preferred_locale)->toBe('en')
        ->and($fresh->name)->toBe('Original Name');
});

test('PINNED: fields outside the two rules are dropped in silence', function (): void {
    // `$user->update($request->validated())` means anything not named in the
    // rules never reaches the model — which is safe, but the caller is told
    // the request succeeded.
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', [
        'name' => 'Updated Name',
        'email' => 'attacker@quran.test',
        'type' => UserType::CONTESTANT,
        'is_active' => false,
    ])->assertOk();

    $fresh = UserModel::query()->find($user->id);

    expect($fresh->name)->toBe('Updated Name')
        ->and($fresh->email)->toBe('self-profile@quran.test')
        ->and((string) $fresh->type)->toBe((string) UserType::ADMIN)
        ->and((bool) $fresh->is_active)->toBeTrue();
});

test('an account-only edit creates no profile row', function (): void {
    // Was pinned as "writing goes straight to users", describing the direct
    // Eloquent call the refactor removed. It still holds for a better reason:
    // UpdateSelfUseCase calls the profile use case only when the request
    // actually carried profile fields, so renaming yourself does not leave an
    // empty row behind for an account that has never had one.
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated Name'])->assertOk();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('PATCH /me requires authentication', function (): void {
    $this->patchJson('/api/v1/me', ['name' => 'Whoever'])->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| The one thing the refactor changed on purpose
|--------------------------------------------------------------------------
|
| Routing through UpdateUserProfileUseCase brought its event with it. Nothing
| above pinned the absence of one, so the change would have passed unnoticed —
| which is exactly why it is asserted here instead of left implicit.
*/

test('changing the name now records an event, and the actor is the account itself', function (): void {
    $user = selfProfileUser();

    Event::fake([UserProfileUpdated::class]);

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated Name'])->assertOk();

    Event::assertDispatched(UserProfileUpdated::class, function (UserProfileUpdated $e) use ($user): bool {
        return $e->userId === (string) $user->id
            && $e->previousName === 'Original Name'
            && $e->name === 'Updated Name'
            // Self-service: the person who did it is the person it happened to.
            && $e->byUserId === (string) $user->id;
    });
});

test('submitting the same name unchanged records nothing', function (): void {
    // The use case only emits when the name actually moved, so a form
    // submitted without edits does not fill the trail with noise.
    $user = selfProfileUser();

    Event::fake([UserProfileUpdated::class]);

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Original Name'])->assertOk();

    Event::assertNotDispatched(UserProfileUpdated::class);
});
