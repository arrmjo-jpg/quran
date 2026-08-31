<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Invitation;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\InvitationToken;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Issues an invitation for a pending account — ADR-016 D14.
 *
 * Written in this story rather than in the one that first needs it, because
 * two later stories depend on the same three decisions — how a token is made,
 * how long it lives, and what makes an account eligible to be invited — and
 * those decisions must have exactly one home. Duplicating them would mean two
 * expiry windows that drift apart, which is the kind of divergence nobody
 * notices until a token outlives what someone believed it did.
 *
 * Returns the plaintext token to its caller. That is the only moment it
 * exists: it is not stored, not logged, and cannot be recovered afterwards.
 * If it is lost, a new one is issued rather than the old one reproduced.
 *
 * ADR-020 D5 is the first caller to invoke this twice for one account: a
 * retry of a failed invitation mail has nothing to re-send, precisely because
 * the token cannot be recovered, so it re-issues. Calling twice therefore
 * REPLACES the open invitation rather than adding a second one -- see the
 * comment at the issue() call.
 */
final class IssueInvitationUseCase
{
    /**
     * Seven days. Long enough to survive a weekend and an out-of-office, short
     * enough that a forgotten invitation in a mailbox is not a standing key to
     * an account. Expressed here rather than in config because changing it is
     * a decision about account security, not an environment setting.
     */
    public const TTL_DAYS = 7;

    public function __construct(
        private UserRepositoryContract $users,
        private InvitationRepositoryContract $invitations,
    ) {}

    /**
     * @return array{invitation: Invitation, token: string} the plaintext token
     *                                                      travels with the result and nowhere else
     */
    public function execute(string $userId, ?string $byUserId = null): array
    {
        return DB::transaction(function () use ($userId, $byUserId): array {
            $id = new UserId($userId);
            $user = $this->users->findOrFail($id);

            // An account that already has a password has been claimed. Issuing
            // an invitation for it would be a password-reset path wearing an
            // invitation's clothes, and reset is its own story with its own
            // rules — notably that it is requested by the account holder, not
            // handed out by an administrator.
            if ($user->getPasswordHash() !== null) {
                throw new DomainException('This account has already been activated; use a password reset instead.');
            }

            $token = InvitationToken::generate();

            // RE-ISSUING REPLACES -- ADR-020 D5.
            //
            // Reusing the open invitation's id turns the repository's
            // updateOrCreate into an update, so the account keeps exactly one
            // invitation and the superseded token stops matching. Without
            // this, a retry would leave two live tokens for one account, and
            // "the open invitation" would be decided by a created_at tie
            // between two rows written in the same second.
            //
            // Until retry existed this branch was unreachable: the use case
            // was called once per account, at creation. So nothing that works
            // today changes shape -- only the second call, which is new.
            $open = $this->invitations->findOpenForUser($id);

            $invitation = Invitation::issue(
                userId: $id,
                token: $token,
                expiresAt: now()->addDays(self::TTL_DAYS)->toIso8601String(),
                invitedBy: $byUserId === null ? null : new UserId($byUserId),
                id: $open?->id,
            );

            $this->invitations->save($invitation);

            return [
                'invitation' => $invitation,
                // @phpstan-ignore-next-line the plaintext exists only on a freshly generated token
                'token' => $token->plaintext,
            ];
        });
    }
}
