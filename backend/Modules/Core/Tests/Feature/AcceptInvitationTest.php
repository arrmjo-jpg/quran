<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AcceptInvitationUseCase;
use Modules\Core\Application\UseCases\IssueInvitationUseCase;
use Modules\Core\Domain\Events\InvitationAccepted;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'invitations');

function invitee(string $email = 'invitee@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Invited Person',
        'type' => UserType::ADMIN,
        'password_hash' => null,
        'is_active' => false,
    ]);
}

/** @return array{user: UserModel, token: string} */
function invited(string $email = 'invitee@quran.test'): array
{
    $user = invitee($email);
    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id);

    return ['user' => $user, 'token' => $result['token']];
}

function acceptRequest(string $token, string $password = 'ChosenPass123!'): array
{
    return ['token' => $token, 'password' => $password, 'password_confirmation' => $password];
}

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

test('accepting sets the password, activates the account and closes the invitation', function (): void {
    ['user' => $user, 'token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token))
        ->assertOk()
        ->assertJsonPath('success', true);

    $fresh = $user->fresh();
    expect($fresh->password_hash)->not->toBeNull();
    expect((bool) $fresh->is_active)->toBeTrue();
    expect(password_verify('ChosenPass123!', $fresh->password_hash))->toBeTrue();

    expect(app(InvitationRepositoryContract::class)->findOpenForUser(new UserId((string) $user->id)))
        ->toBeNull();
});

test('the account can then sign in with the password its owner chose', function (): void {
    // The point of the whole flow, asserted end to end rather than by
    // inspecting a column: no administrator ever knew this password.
    ['user' => $user, 'token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token))->assertOk();

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => $user->email,
        'password' => 'ChosenPass123!',
    ])->assertOk()->assertJsonPath('success', true);
});

test('no session is issued by accepting', function (): void {
    // Setting a password and being signed in are separate acts. Login owns MFA,
    // device trust and the deactivation check; this endpoint reimplements none
    // of them, so it must not hand out a token either.
    ['token' => $token] = invited();

    $response = $this->postJson('/api/v1/invitations/accept', acceptRequest($token));

    expect($response->json())->not->toHaveKey('data.token');
    expect(json_encode($response->json()))->not->toContain('Bearer');
});

test('acceptance is recorded for the audit trail', function (): void {
    Event::fake([InvitationAccepted::class]);

    ['user' => $user, 'token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token))->assertOk();

    Event::assertDispatched(InvitationAccepted::class, function (InvitationAccepted $e) use ($user): bool {
        return $e->userId === (string) $user->id && $e->occurredAt !== '';
    });
});

/*
|--------------------------------------------------------------------------
| Every refusal looks identical from outside
|--------------------------------------------------------------------------
|
| A public endpoint whose input is a secret must not become an oracle. These
| assert the sameness deliberately: if a future change starts distinguishing
| "expired" from "unknown" in the response, these fail.
*/

test('an unknown token is refused', function (): void {
    $this->postJson('/api/v1/invitations/accept', acceptRequest(bin2hex(random_bytes(32))))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_INVITATION');
});

test('an expired token is refused, and identically', function (): void {
    ['user' => $user, 'token' => $token] = invited();

    DB::table('invitations')->where('user_id', $user->id)
        ->update(['expires_at' => now()->subSecond()]);

    $expired = $this->postJson('/api/v1/invitations/accept', acceptRequest($token));
    $unknown = $this->postJson('/api/v1/invitations/accept', acceptRequest(bin2hex(random_bytes(32))));

    $expired->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INVITATION');

    // Byte-identical responses. Anything else answers "does this token exist?"
    expect($expired->json())->toBe($unknown->json());

    expect($user->fresh()->password_hash)->toBeNull();
    expect((bool) $user->fresh()->is_active)->toBeFalse();
});

test('a token cannot be used twice', function (): void {
    ['user' => $user, 'token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token))->assertOk();

    $second = $this->postJson('/api/v1/invitations/accept', acceptRequest($token, 'SomeoneElse456!'));
    $second->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INVITATION');

    // And the first password still stands — the replay changed nothing.
    expect(password_verify('ChosenPass123!', $user->fresh()->password_hash))->toBeTrue();
});

test('an invitation cannot overwrite a password set another way', function (): void {
    // An old link outliving its activation must not become a credential reset.
    ['user' => $user, 'token' => $token] = invited();

    $user->update(['password_hash' => password_hash('SetElsewhere1!', PASSWORD_BCRYPT)]);

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token, 'Hijacked999!'))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_INVITATION');

    expect(password_verify('SetElsewhere1!', $user->fresh()->password_hash))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Nothing half-happens
|--------------------------------------------------------------------------
*/

test('a refused acceptance leaves the invitation open', function (): void {
    // The three writes share a transaction. A consumed token with no password
    // would strand the account with nobody able to set one.
    ['user' => $user, 'token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', ['token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertStatus(422);

    $open = app(InvitationRepositoryContract::class)->findOpenForUser(new UserId((string) $user->id));

    expect($open)->not->toBeNull();
    expect($open->isAccepted())->toBeFalse();

    // And the original token still works afterwards.
    $this->postJson('/api/v1/invitations/accept', acceptRequest($token))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Input rules
|--------------------------------------------------------------------------
*/

test('a password that was not retyped is refused', function (): void {
    // Nobody can set this password for them later — D14 forbids it — so a
    // mistyped one locks the account out permanently.
    ['token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', [
        'token' => $token,
        'password' => 'ChosenPass123!',
        'password_confirmation' => 'DifferentPass123!',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a short password is refused', function (): void {
    ['token' => $token] = invited();

    $this->postJson('/api/v1/invitations/accept', acceptRequest($token, 'short'))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('the endpoint needs no authentication', function (): void {
    // The invitee has no account to sign into yet. If this ever starts
    // returning 401 the whole flow is unreachable.
    $this->postJson('/api/v1/invitations/accept', acceptRequest('nope'))
        ->assertStatus(422);
});

test('there is no endpoint that reads an invitation', function (): void {
    // A GET answering "this token is valid and belongs to alice@example.com"
    // would be a probe with a reward attached. Asserted against the route
    // table so it cannot be added without this failing.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_contains($r->uri(), 'invitation'))
        ->map(fn ($r): string => implode('|', $r->methods()).' '.$r->uri())
        ->values()
        ->all();

    expect($routes)->toBe(['POST api/v1/invitations/accept']);
});
