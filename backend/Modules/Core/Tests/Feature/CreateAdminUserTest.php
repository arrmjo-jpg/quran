<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Mail\InvitationMail;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'invitations');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
    Mail::fake();
});

function creatorAdmin(string $email = 'creator@quran.test'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Creating Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function createUserPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'newcomer@quran.test',
        'name' => 'New Comer',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| The account it creates
|--------------------------------------------------------------------------
*/

test('the account is created pending, with no password and not active', function (): void {
    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', 'newcomer@quran.test')
        ->assertJsonPath('data.is_active', false);

    $created = UserModel::query()->where('email', 'newcomer@quran.test')->first();

    expect($created->password_hash)->toBeNull();
    expect((bool) $created->is_active)->toBeFalse();
    expect($created->type)->toBe(UserType::ADMIN);
});

test('an invitation is issued for it', function (): void {
    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertCreated();

    $created = UserModel::query()->where('email', 'newcomer@quran.test')->first();

    expect(DB::table('invitations')->where('user_id', $created->id)->count())->toBe(1);
});

test('roles chosen at creation are applied', function (): void {
    $admin = creatorAdmin();
    $roleId = app(RoleRepositoryContract::class)->findByName('moderator')->id->value;

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/users', createUserPayload(['roles' => [$roleId]]))
        ->assertCreated()
        ->assertJsonPath('data.roles', ['moderator']);
});

/*
|--------------------------------------------------------------------------
| The secret never reaches the administrator
|--------------------------------------------------------------------------
|
| ADR-016 D14 in its strictest form. An administrator who could read the link
| could open it, set a password and sign in as the invitee — which would make
| them the one who chose the credential, exactly what D14 forbids.
*/

test('no token, link or password appears anywhere in the response', function (): void {
    $response = $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload());

    $response->assertCreated();

    $created = UserModel::query()->where('email', 'newcomer@quran.test')->first();
    $tokenHash = DB::table('invitations')->where('user_id', $created->id)->value('token_hash');

    $body = json_encode($response->json());

    expect($body)->not->toContain('token');
    expect($body)->not->toContain($tokenHash);
    expect($body)->not->toContain('invitations/accept');
    expect($body)->not->toContain('password');
});

test('the emailed link is the only carrier of the token', function (): void {
    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertCreated();

    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail): bool {
        return $mail->hasTo('newcomer@quran.test')
            && str_contains($mail->acceptUrl, 'token=')
            && str_starts_with($mail->acceptUrl, config('core.admin_url'));
    });
});

test('the emailed token is the one that actually works', function (): void {
    // Ties the two halves together: without this, the mail could carry a
    // token that no invitation recognises and every other test would pass.
    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertCreated();

    $sent = null;
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$sent): bool {
        $sent = $mail;

        return true;
    });

    parse_str((string) parse_url($sent->acceptUrl, PHP_URL_QUERY), $query);

    $this->postJson('/api/v1/invitations/accept', [
        'token' => $query['token'],
        'password' => 'InviteeChosen1!',
        'password_confirmation' => 'InviteeChosen1!',
    ])->assertOk();

    $created = UserModel::query()->where('email', 'newcomer@quran.test')->first();
    expect(password_verify('InviteeChosen1!', $created->password_hash))->toBeTrue();
    expect((bool) $created->is_active)->toBeTrue();
});

test('the link points at the admin panel, not at the API', function (): void {
    // APP_URL is the API's address; an invitee following it gets JSON instead
    // of the form that lets them choose a password.
    //
    // Compared as ORIGINS. The first version asked whether acceptUrl starts
    // with config('app.url'), which answers a different question whenever one
    // URL is a textual prefix of the other. APP_URL=http://localhost with
    // ADMIN_URL=http://localhost:5173 -- the values in .env.example -- differ
    // in origin but not in prefix, so a perfectly correct link was read as
    // pointing at the API. It passed on the machine it was written on for the
    // sole reason that APP_URL carried a port there, and failed on the first
    // runner that copied .env.example.
    //
    // Stated positively too: the requirement is that the link points AT the
    // admin panel, which is the thing worth asserting. It also stays correct
    // where the two are deliberately configured to the same origin, which the
    // old `||` escape hatch existed to allow.
    $origin = static function (?string $url): string {
        $parts = parse_url((string) $url) ?: [];

        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    };

    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertCreated();

    Mail::assertSent(
        InvitationMail::class,
        fn (InvitationMail $mail): bool => $origin($mail->acceptUrl) === $origin(config('core.admin_url'))
    );
});

/*
|--------------------------------------------------------------------------
| Authorisation and refusals
|--------------------------------------------------------------------------
*/

test('creating a user requires users.create', function (): void {
    $admin = creatorAdmin();
    $viewer = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'viewer@quran.test',
        'name' => 'Viewer',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $role = app(CreateRoleUseCase::class)->execute('user_viewer', ['users.view'], (string) $admin->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $viewer->id, $role->id->value, (string) $admin->id);

    $this->actingAs($viewer)->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($viewer)
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertForbidden();

    expect(UserModel::query()->where('email', 'newcomer@quran.test')->exists())->toBeFalse();
});

test('an actor cannot create a user holding roles beyond their own', function (): void {
    // PE-1, applied at creation rather than discovered later when the invitee
    // signs in with more authority than the person who invited them.
    $admin = creatorAdmin();
    $limited = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'limited@quran.test',
        'name' => 'Limited',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $weak = app(CreateRoleUseCase::class)
        ->execute('inviter_only', ['users.view', 'users.create', 'users.assign_roles'], (string) $admin->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $limited->id, $weak->id->value, (string) $admin->id);

    $superAdminId = app(RoleRepositoryContract::class)->findByName('super_admin')->id->value;

    $this->actingAs($limited)
        ->postJson('/api/v1/admin/users', createUserPayload(['roles' => [$superAdminId]]))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'PRIVILEGE_ESCALATION');

    expect(UserModel::query()->where('email', 'newcomer@quran.test')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

test('a duplicate email is refused', function (): void {
    $admin = creatorAdmin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/users', createUserPayload(['email' => $admin->email]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('the request has no password field to supply', function (): void {
    // Sending one must not set one. If this ever starts working, D14 is gone.
    $this->actingAs(creatorAdmin())
        ->postJson('/api/v1/admin/users', createUserPayload([
            'password' => 'AdminChose123!',
            'password_confirmation' => 'AdminChose123!',
        ]))
        ->assertCreated();

    $created = UserModel::query()->where('email', 'newcomer@quran.test')->first();

    expect($created->password_hash)->toBeNull();
});

test('a contestant cannot reach the endpoint', function (): void {
    $contestant = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'contestant@quran.test',
        'name' => 'Contestant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $this->actingAs($contestant)
        ->postJson('/api/v1/admin/users', createUserPayload())
        ->assertForbidden();
});
