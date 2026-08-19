<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\IssueInvitationUseCase;
use Modules\Core\Domain\Entities\Invitation;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\ValueObjects\InvitationToken;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'invitations');

/** An account created but never claimed: no password, not active. */
function pendingAccount(string $email = 'pending@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Pending Account',
        'type' => UserType::ADMIN,
        'password_hash' => null,
        'is_active' => false,
    ]);
}

function claimedAccount(string $email = 'claimed@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Claimed Account',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function invitations(): InvitationRepositoryContract
{
    return app(InvitationRepositoryContract::class);
}

/*
|--------------------------------------------------------------------------
| The token
|--------------------------------------------------------------------------
*/

test('the plaintext token is never stored', function (): void {
    // The single rule this whole design rests on. A database disclosure must
    // not hand over the ability to claim accounts.
    $user = pendingAccount();

    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id);
    $plaintext = $result['token'];

    $stored = DB::table('invitations')->where('user_id', $user->id)->first();

    expect($stored->token_hash)->not->toBe($plaintext);
    expect($stored->token_hash)->toBe(hash('sha256', $plaintext));

    // And nowhere else in the row either.
    expect(json_encode($stored))->not->toContain($plaintext);
});

test('a token rebuilt from storage cannot produce its plaintext', function (): void {
    // fromHash() exists so a repository can rehydrate without being able to
    // mint the secret. If this ever returns a string, the guarantee is gone.
    $token = InvitationToken::fromHash(str_repeat('a', 64));

    expect($token->plaintext)->toBeNull();
});

test('a generated token matches only itself', function (): void {
    $token = InvitationToken::generate();

    expect($token->matches($token->plaintext))->toBeTrue();
    expect($token->matches('not-the-token'))->toBeFalse();
    expect($token->matches(strtoupper($token->plaintext)))->toBeFalse();
});

test('a malformed hash is refused', function (): void {
    expect(fn () => InvitationToken::fromHash('too-short'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => InvitationToken::fromHash(str_repeat('Z', 64)))
        ->toThrow(InvalidArgumentException::class);
});

test('two invitations never share a token', function (): void {
    $a = InvitationToken::generate();
    $b = InvitationToken::generate();

    expect($a->hash)->not->toBe($b->hash);
});

/*
|--------------------------------------------------------------------------
| The aggregate's invariants
|--------------------------------------------------------------------------
*/

test('an invitation can be accepted exactly once', function (): void {
    $invitation = Invitation::issue(
        userId: new UserId((string) Str::uuid()),
        token: InvitationToken::generate(),
        expiresAt: now()->addDay()->toIso8601String(),
    );

    $invitation->accept(now()->toIso8601String());

    expect($invitation->isAccepted())->toBeTrue();
    expect(fn () => $invitation->accept(now()->toIso8601String()))
        ->toThrow(DomainException::class, 'already been accepted');
});

test('an expired invitation cannot be accepted', function (): void {
    $invitation = Invitation::issue(
        userId: new UserId((string) Str::uuid()),
        token: InvitationToken::generate(),
        expiresAt: now()->subSecond()->toIso8601String(),
    );

    expect($invitation->isExpired(now()->toIso8601String()))->toBeTrue();
    expect(fn () => $invitation->accept(now()->toIso8601String()))
        ->toThrow(DomainException::class, 'expired');
    expect($invitation->isAccepted())->toBeFalse();
});

test('expiry is evaluated at the boundary, not approximately', function (): void {
    $at = now()->toIso8601String();

    $invitation = Invitation::issue(
        userId: new UserId((string) Str::uuid()),
        token: InvitationToken::generate(),
        expiresAt: $at,
    );

    // Exactly at the expiry instant the invitation is already gone. Chosen
    // over "still valid at the boundary" because a token whose window has
    // arithmetically ended should not depend on which side of a millisecond a
    // request lands.
    expect($invitation->isExpired($at))->toBeTrue();
    expect($invitation->isExpired(now()->subSecond()->toIso8601String()))->toBeFalse();
});

test('a matching token on a dead invitation still matches', function (): void {
    // "Wrong token" and "too late" must stay distinguishable — they are
    // different things to tell a person, and conflating them would make a
    // valid user think they had the wrong link.
    $token = InvitationToken::generate();
    $invitation = Invitation::issue(
        userId: new UserId((string) Str::uuid()),
        token: $token,
        expiresAt: now()->subDay()->toIso8601String(),
    );

    expect($invitation->tokenMatches($token->plaintext))->toBeTrue();
    expect($invitation->isRedeemable(now()->toIso8601String()))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Issuing
|--------------------------------------------------------------------------
*/

test('issuing records who invited and when it expires', function (): void {
    $inviter = claimedAccount('inviter@quran.test');
    $user = pendingAccount();

    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id, (string) $inviter->id);

    $invitation = $result['invitation'];
    expect($invitation->invitedBy?->value)->toBe((string) $inviter->id);
    expect($invitation->isRedeemable(now()->toIso8601String()))->toBeTrue();
    expect($invitation->isRedeemable(now()->addDays(IssueInvitationUseCase::TTL_DAYS)->toIso8601String()))->toBeFalse();
});

test('an already-activated account cannot be invited', function (): void {
    // Otherwise "invite" becomes an administrator-initiated password reset,
    // which is exactly what D14 refuses.
    $user = claimedAccount();

    expect(fn () => app(IssueInvitationUseCase::class)->execute((string) $user->id))
        ->toThrow(DomainException::class, 'already been activated');

    expect(DB::table('invitations')->where('user_id', $user->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The repository
|--------------------------------------------------------------------------
*/

test('an invitation round-trips through storage', function (): void {
    $user = pendingAccount();
    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id);

    $found = invitations()->findByToken($result['token']);

    expect($found)->not->toBeNull();
    expect($found->id->value)->toBe($result['invitation']->id->value);
    expect($found->userId->value)->toBe((string) $user->id);
    expect($found->tokenMatches($result['token']))->toBeTrue();
});

test('an unknown token finds nothing', function (): void {
    expect(invitations()->findByToken(bin2hex(random_bytes(32))))->toBeNull();
});

test('acceptance survives a save and reload', function (): void {
    $user = pendingAccount();
    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id);

    $invitation = invitations()->findByToken($result['token']);
    $invitation->accept(now()->toIso8601String());
    invitations()->save($invitation);

    $reloaded = invitations()->findByToken($result['token']);

    expect($reloaded->isAccepted())->toBeTrue();
    expect($reloaded->isRedeemable(now()->toIso8601String()))->toBeFalse();
});

test('an accepted invitation is no longer the open one', function (): void {
    // What Epic 12's resend and the users screen both ask.
    $user = pendingAccount();
    $result = app(IssueInvitationUseCase::class)->execute((string) $user->id);

    expect(invitations()->findOpenForUser(new UserId((string) $user->id)))->not->toBeNull();

    $invitation = invitations()->findByToken($result['token']);
    $invitation->accept(now()->toIso8601String());
    invitations()->save($invitation);

    expect(invitations()->findOpenForUser(new UserId((string) $user->id)))->toBeNull();
});

test('an expired but unaccepted invitation is still the open one', function (): void {
    // "Never invited" and "invited, link went stale" are different states, and
    // resend needs to tell them apart.
    $user = pendingAccount();
    app(IssueInvitationUseCase::class)->execute((string) $user->id);

    DB::table('invitations')->where('user_id', $user->id)
        ->update(['expires_at' => now()->subDay()]);

    $open = invitations()->findOpenForUser(new UserId((string) $user->id));

    expect($open)->not->toBeNull();
    expect($open->isExpired(now()->toIso8601String()))->toBeTrue();
});

test('the schema declares that invitations cannot outlive their account', function (): void {
    // A DECLARATION CHECK, NOT A BEHAVIOUR CHECK, and the distinction is worth
    // stating rather than hiding behind a green tick.
    //
    // The cascade cannot be exercised here: the suite runs with
    // DB_FOREIGN_KEYS=false, and SQLite refuses to change that pragma inside a
    // transaction, which is what RefreshDatabase wraps every test in.
    //
    // It also would not fire in production. Accounts are soft-deleted, and
    // AccountDeletionTest now forbids both forceDelete() and
    // DB::table('users')->delete() — so no application path reaches a hard
    // delete at all. The constraint is a database-level backstop for a case
    // the application layer has been closed against, which makes asserting it
    // is *declared* the honest test, and asserting it *fires* a test of
    // something nothing does.
    $migration = glob(base_path('Modules/Core/Database/Migrations/*_create_invitations_table.php'));

    expect($migration)->toHaveCount(1);

    $source = (string) file_get_contents($migration[0]);

    expect($source)->toContain('cascadeOnDelete');
    expect($source)->toContain('fk_invitations_user_id');

    // The inviter is history, not a dependency: losing their account must not
    // take the invitation with it.
    expect($source)->toContain('nullOnDelete');
    expect($source)->toContain('fk_invitations_invited_by_user_id');
});
