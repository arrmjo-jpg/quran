<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'golden-master');

/*
|--------------------------------------------------------------------------
| The account's language, as the login response carries it — ADR-019 D3, D10
|--------------------------------------------------------------------------
|
| DELIBERATELY SMALL. `PATCH /me` is already characterised in full by
| SelfProfileGoldenMasterTest, which pins that it updates the locale, accepts
| `en` and `es`, refuses `fr` with 422 while leaving the column untouched, and
| that a partial update does not clobber the other field. None of that is
| repeated here — Epic 7 depends on that contract and does not change it, so
| duplicating it would add maintenance and prove nothing new.
|
| WHAT WAS NOT PINNED, AND IS THE REASON THIS FILE EXISTS.
|
| ADR-019 D3 has the admin panel reconcile its language from the LOGIN
| RESPONSE rather than issuing a second request, because that response already
| carries `preferred_locale`. Nothing asserted that it does. Searched: six test
| files call `auth/login`, and none of them mentions the field.
|
| So the one fact Epic 7 leans on was the one fact no test held. Trimming
| UserResource, or swapping the login handler's resource, would have broken
| language reconciliation silently — the login would still succeed, and the
| interface would simply stop following the account.
|
| These assertions are DESCRIPTIVE of behaviour that exists today. They are
| written before the front end starts depending on it, which is the only
| moment at which recording it proves anything.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function languageUser(string $locale): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'language@quran.test',
        'name' => 'Language User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'preferred_locale' => $locale,
    ]);
}

function loginAsLanguageUser(): TestResponse
{
    return test()->postJson('/api/v1/admin/auth/login', [
        'email' => 'language@quran.test',
        'password' => 'Pass123!',
    ]);
}

test('the login response carries the account language', function (): void {
    languageUser('es');

    $response = loginAsLanguageUser();

    $response->assertOk()->assertJsonPath('data.user.preferred_locale', 'es');
})->group('golden-master');

test('it is the stored value, not a default', function (): void {
    // The assertion above would pass on a hardcoded default too if the account
    // happened to hold it. Each supported language is checked against an
    // account that actually holds it, so a constant cannot satisfy all three.
    foreach (['ar', 'en', 'es'] as $locale) {
        UserModel::query()->where('email', 'language@quran.test')->forceDelete();
        languageUser($locale);

        loginAsLanguageUser()->assertOk()->assertJsonPath('data.user.preferred_locale', $locale);
    }
})->group('golden-master');

test('the MFA challenge branch carries no user, so nothing can be read from it', function (): void {
    // The reconciliation point matters: an account with MFA enabled does not
    // receive `data.user` at all -- only a challenge token. A client reading
    // the language from the login response must therefore read it after the
    // challenge completes, not from the first response.
    //
    // Recorded because it is the case that would make a correct-looking
    // implementation wrong for exactly the accounts that use MFA.
    $user = languageUser('es');
    $user->update(['mfa_enabled' => true]);

    $response = loginAsLanguageUser();

    $response->assertOk()->assertJsonPath('data.mfa_required', true);
    expect($response->json('data'))->not->toHaveKey('user');
})->group('golden-master');

test('GET /me carries it too, for a session that outlives the login response', function (): void {
    // The fallback path: a reload has a token but no login response to read.
    // The key list itself is pinned by SelfProfileGoldenMasterTest; this pins
    // that the VALUE follows the account.
    $user = languageUser('en');

    $this->actingAs($user)->getJson('/api/v1/admin/auth/me')
        ->assertOk()
        ->assertJsonPath('data.preferred_locale', 'en');
})->group('golden-master');
