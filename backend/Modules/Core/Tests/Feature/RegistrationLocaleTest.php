<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'locales');

/*
|--------------------------------------------------------------------------
| Public registration speaks the same languages as everything else
|--------------------------------------------------------------------------
|
| `59e2012` narrowed the Locale value object to ar/en/es — the languages the
| panel ships — and fixed two admin endpoints that were answering 500 because
| their request classes accepted `es` while the domain refused it.
|
| RegisterUserRequest was deliberately left alone in that commit: public
| registration is a different surface from self-service, and unifying the two
| was its own decision rather than something swept in. But leaving it changed
| its nature. Before, it accepted `fr` and so did the domain — a silent
| inconsistency with the interface, and nothing more. After, it accepts `fr`
| and the domain refuses it, which is a 500 waiting for whoever registers in
| French.
|
| These tests were written before the fix. The `fr` case failed with exactly
| that 500, which is the only way to know the test describes the defect it
| claims to.
*/

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Contestant',
        'email' => 'new-contestant@quran.test',
        'password' => 'Pass123!',
    ], $overrides);
}

test('registering in Spanish works', function (): void {
    // Was a 422 from the request: `es` is what the panel ships and what
    // CreateAdminUserRequest and UpdateProfileRequest already accept, but this
    // one had never been updated.
    $this->postJson('/api/v1/auth/register', registrationPayload(['locale' => 'es']))
        ->assertCreated();

    expect(UserModel::query()->where('email', 'new-contestant@quran.test')->value('preferred_locale'))
        ->toBe('es');
});

test('registering in French is refused, not answered with a 500', function (): void {
    // The defect this story exists for. The request validated `fr`, then
    // AuthController::register built `new Locale('fr')` and the value object
    // threw with nothing catching it.
    $this->postJson('/api/v1/auth/register', registrationPayload(['locale' => 'fr']))
        ->assertStatus(422);

    expect(UserModel::query()->where('email', 'new-contestant@quran.test')->exists())->toBeFalse();
});

test('the languages that always worked still work', function (string $locale): void {
    // The fix narrows a list, so what it must not do is narrow it too far.
    $this->postJson('/api/v1/auth/register', registrationPayload([
        'email' => "contestant-{$locale}@quran.test",
        'locale' => $locale,
    ]))->assertCreated();

    expect(UserModel::query()->where('email', "contestant-{$locale}@quran.test")->value('preferred_locale'))
        ->toBe($locale);
})->with(['ar', 'en']);

test('registering without a locale still defaults to Arabic', function (): void {
    // `locale` is nullable and AuthController passes 'ar' when it is absent.
    // Narrowing the accepted set must not disturb the caller who sends none.
    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();

    expect(UserModel::query()->where('email', 'new-contestant@quran.test')->value('preferred_locale'))
        ->toBe('ar');
});
