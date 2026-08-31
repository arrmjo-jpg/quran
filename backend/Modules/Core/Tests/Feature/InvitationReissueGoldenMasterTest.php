<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\IssueInvitationUseCase;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\InvitationModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'invitations', 'golden-master');

/*
|--------------------------------------------------------------------------
| Issuing twice for one account — what is true BEFORE ADR-020 D5
|--------------------------------------------------------------------------
|
| WHY THIS FILE EXISTS NOW, HAVING NOT BEEN NEEDED BEFORE.
|
| IssueInvitationUseCase has always been called exactly once per account, at
| creation, and it refuses an account that already has a password. So "issue a
| second invitation for the same pending account" was unreachable, and nothing
| ever pinned what it does.
|
| ADR-020 D5 reaches it. A retry of a failed `invitation.created` cannot
| reproduce the original mail -- IssueInvitationUseCase:26 states the plaintext
| token exists for one moment and cannot be recovered -- so a retry must issue
| a fresh invitation. That makes the second call real, and this records what it
| currently does before that behaviour is changed.
|
| WHAT IT CURRENTLY DOES, and why it is not acceptable to ship: save() is an
| updateOrCreate keyed on the INVITATION id, and issue() mints a new id every
| time. So a second issue does not replace the first. It adds. The account ends
| up with two unaccepted invitations and TWO WORKING TOKENS.
|
| DESCRIPTIVE, NOT PRESCRIPTIVE. Every assertion below records behaviour this
| story intends to invert, written before the change because a golden master
| taken afterwards proves nothing.
*/

function reissueTarget(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'reissue@quran.test',
        'name' => 'Reissue Target',
        'type' => UserType::ADMIN,
        'password_hash' => null,
        'is_active' => false,
    ]);
}

function issueFor(UserModel $user): string
{
    return app(IssueInvitationUseCase::class)->execute((string) $user->id)['token'];
}

test('GOLDEN MASTER: issuing twice replaces the invitation instead of adding one', function (): void {
    $user = reissueTarget();

    issueFor($user);
    issueFor($user);

    // BEFORE: 2. save() keys on the invitation id and issue() generates a new
    // one, so the second issue inserted rather than replaced.
    // AFTER (ADR-020 D5): 1 -- a pending account has at most one open
    // invitation, and re-issuing replaces it in place.
    expect(InvitationModel::query()->where('user_id', $user->id)->count())->toBe(1);
})->group('golden-master');

test('GOLDEN MASTER: the superseded token stops opening the account', function (): void {
    $user = reissueTarget();

    $first = issueFor($user);
    $second = issueFor($user);

    expect($first)->not->toBe($second);

    // BEFORE: both matched. A retry would have left two live credentials for
    // one account, and the older would have lived out its full seven days in
    // whatever mailbox eventually received it.
    // AFTER: only the current one matches. Re-issuing supersedes, so a retry
    // does not multiply the ways into an account.
    expect(invitationsRepo()->findByToken($second))->not->toBeNull()
        ->and(invitationsRepo()->findByToken($first))->toBeNull();
})->group('golden-master');

test('GOLDEN MASTER: which invitation is open is now an invariant, not a tie-break', function (): void {
    $user = reissueTarget();

    $first = issueFor($user);
    $second = issueFor($user);

    $rows = InvitationModel::query()->where('user_id', $user->id)->get();

    // BEFORE: two rows sharing a created_at, so findOpenForUser's
    // `orderByDesc('created_at')` had nothing to order by and the row it
    // returned was a storage-engine tie-break -- which resolved to the
    // SUPERSEDED invitation. A retry on that code would have minted a token
    // the platform did not consider current, while the one it did consider
    // current was the one already known not to have arrived.
    // AFTER: one row. The ordering never has to break a tie because there is
    // never a tie.
    expect($rows)->toHaveCount(1);

    $open = invitationsRepo()->findOpenForUser(new UserId((string) $user->id));

    expect($open)->not->toBeNull()
        ->and($open->tokenMatches($second))->toBeTrue()
        ->and($open->tokenMatches($first))->toBeFalse()
        // The id survives the re-issue. That is the mechanism: same row,
        // new token hash and new expiry written over the old.
        ->and($open->id->value)->toBe((string) $rows->first()->id);
})->group('golden-master');

function invitationsRepo(): InvitationRepositoryContract
{
    return app(InvitationRepositoryContract::class);
}
