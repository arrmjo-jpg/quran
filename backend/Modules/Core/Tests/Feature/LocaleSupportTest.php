<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'locales');

/*
|--------------------------------------------------------------------------
| One locale set, agreed on by the HTTP layer and the domain
|--------------------------------------------------------------------------
|
| These tests exist because the two disagreed. Every request class that writes
| `users.preferred_locale` accepted `es`, and the Locale value object behind
| the column did not — so the validation passed and the domain threw, with
| nothing catching it. Two admin endpoints answered 500 for a language the
| panel ships.
|
| The tests were written before the fix and failed on the two 500s, which is
| the only way to know they test the thing they claim to.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function localeAdmin(string $email = 'locale-admin@quran.test'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Locale Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/*
|--------------------------------------------------------------------------
| The value object
|--------------------------------------------------------------------------
*/

test('the domain accepts exactly the languages the panel ships', function (string $locale): void {
    expect((new Locale($locale))->value)->toBe($locale);
})->with(['ar', 'en', 'es']);

test('the domain refuses a language with no translations', function (): void {
    // `fr` was permitted until 2026-08-20 and had no translations anywhere in
    // frontend-admin. Nothing in the platform could render it.
    expect(fn () => new Locale('fr'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported locale: fr');
});

/*
|--------------------------------------------------------------------------
| The two admin endpoints that answered 500
|--------------------------------------------------------------------------
*/

test('an administrator can be created in Spanish', function (): void {
    // Was a 500: CreateAdminUserRequest validated `es`, then
    // CreateAdminUserUseCase built `new Locale('es')` and the value object
    // threw with nothing catching it.
    $admin = localeAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'email' => 'spanish-admin@quran.test',
        'name' => 'Spanish Admin',
        'locale' => 'es',
    ])->assertCreated();

    expect(UserModel::query()->where('email', 'spanish-admin@quran.test')->value('preferred_locale'))
        ->toBe('es');
});

test('an administrator can set another account to Spanish', function (): void {
    // The same 500 on the other path, and this one had no try/catch at all in
    // UserController::update.
    $admin = localeAdmin();

    $target = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'target@quran.test',
        'name' => 'Target',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'preferred_locale' => 'ar',
    ]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", [
        'name' => 'Target',
        'locale' => 'es',
    ])->assertOk();

    expect(UserModel::query()->find($target->id)->preferred_locale)->toBe('es');
});
