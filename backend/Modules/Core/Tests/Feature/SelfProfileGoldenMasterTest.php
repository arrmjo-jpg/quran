<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

test('GET /me returns the account, and the shape it has always returned', function (): void {
    $user = selfProfileUser();

    $response = $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'name', 'email', 'type', 'status', 'is_active',
        'email_verified', 'avatar', 'roles', 'permissions', 'preferred_locale',
    ]);
});

test('PINNED: avatar is always null', function (): void {
    // UserResource hardcodes it in both branches. The key has been in the
    // contract since before there was anywhere for an avatar to live, and
    // nothing in frontend-admin reads it. Story 1 created the column it was
    // always waiting for; Story 3 is what fills it.
    $user = selfProfileUser();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.avatar', null);
});

test('PINNED: no profile fields are exposed', function (): void {
    // display_name, bio and social_links exist in the database as of Story 1
    // and reach nobody.
    $user = selfProfileUser();

    $data = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->json('data');

    expect($data)->not->toHaveKey('display_name')
        ->and($data)->not->toHaveKey('bio')
        ->and($data)->not->toHaveKey('social_links')
        ->and($data)->not->toHaveKey('profile');
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

test('PINNED: writing goes straight to users and no profile row appears', function (): void {
    // The endpoint bypasses every use case in Core and calls the Eloquent
    // model directly — the last place in this module that still does. Story 3
    // is what moves it onto UpdateProfileUseCase.
    $user = selfProfileUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated Name'])->assertOk();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('PATCH /me requires authentication', function (): void {
    $this->patchJson('/api/v1/me', ['name' => 'Whoever'])->assertUnauthorized();
});
