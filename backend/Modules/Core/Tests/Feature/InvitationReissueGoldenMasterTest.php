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

test('GOLDEN MASTER: issuing twice leaves two invitation rows, not one', function (): void {
    $user = reissueTarget();

    issueFor($user);
    issueFor($user);

    // BEFORE: 2. save() keys on the invitation id and issue() generates a new
    // one, so the second issue inserts rather than replaces.
    // AFTER (ADR-020 D5): 1 -- a pending account has at most one open
    // invitation, and re-issuing replaces it in place.
    expect(InvitationModel::query()->where('user_id', $user->id)->count())->toBe(2);
})->group('golden-master');

test('GOLDEN MASTER: the superseded token still opens the account', function (): void {
    $user = reissueTarget();

    $first = issueFor($user);
    $second = issueFor($user);

    expect($first)->not->toBe($second);

    // BEFORE: both match. A retry would therefore leave two live credentials
    // for one account, and the older one lives out its full seven days in
    // whatever mailbox eventually receives it.
    // AFTER: the first token matches nothing -- re-issuing supersedes.
    expect(invitationsRepo()->findByToken($first))->not->toBeNull()
        ->and(invitationsRepo()->findByToken($second))->not->toBeNull();
})->group('golden-master');

test('GOLDEN MASTER: with two rows open, nothing decides which one counts', function (): void {
    $user = reissueTarget();

    $first = issueFor($user);
    $second = issueFor($user);

    $rows = InvitationModel::query()->where('user_id', $user->id)->get();

    // THE CAUSE, asserted rather than described. findOpenForUser orders by
    // created_at DESC and takes the first row. Both issues land in the same
    // second, so DESC has nothing to order BY: the tie is broken by whatever
    // the storage engine happens to return.
    expect($rows->pluck('created_at')->map->toIso8601String()->unique())->toHaveCount(1);

    $open = invitationsRepo()->findOpenForUser(new UserId((string) $user->id));

    // And it is not the newer one. The invitation the platform treats as open
    // is the SUPERSEDED one -- so a retry built on today's code would mint a
    // token that the rest of the system does not consider current, while the
    // token it does consider current is the one already known to have failed
    // to arrive.
    expect($open)->not->toBeNull()
        ->and($open->tokenMatches($first))->toBeTrue()
        ->and($open->tokenMatches($second))->toBeFalse();

    // AFTER (ADR-020 D5): the question stops existing. One row, re-issued in
    // place, so "the open one" is an invariant rather than a tie-break.
})->group('golden-master');

function invitationsRepo(): InvitationRepositoryContract
{
    return app(InvitationRepositoryContract::class);
}
