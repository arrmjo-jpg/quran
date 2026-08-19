<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\InvitationAccepted;
use Modules\Core\Domain\Exceptions\InvalidInvitationException;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\PasswordHash;

/**
 * Claims a pending account — ADR-016 D14, Story 3.
 *
 * The only path from "created" to "usable". An administrator creates the
 * account and never sets its password; the person named on it does, here.
 *
 * THREE WRITES, ONE TRANSACTION: the password is set, the account is
 * activated, and the invitation is marked used. Any two of those without the
 * third is a broken state — a claimed token with no password would strand the
 * account permanently, and an activated account with an unused token would
 * leave a second live key to it.
 *
 * WHY EVERY FAILURE LOOKS THE SAME FROM OUTSIDE: an unknown token, a token
 * that does not match, an expired one and an already-used one all raise
 * InvalidInvitationException. This endpoint is public and its input is a
 * secret, so distinguishing them in the response would turn it into an oracle
 * that answers "does this token exist?" one guess at a time. The distinction
 * that matters to a real invitee — "your link has expired, ask for a new
 * one" — is Epic 12's resend flow to deliver, not a hint on a public endpoint.
 */
final class AcceptInvitationUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private InvitationRepositoryContract $invitations,
    ) {}

    public function execute(string $plaintextToken, string $plainPassword): User
    {
        return DB::transaction(function () use ($plaintextToken, $plainPassword): User {
            $invitation = $this->invitations->findByToken($plaintextToken);

            if ($invitation === null) {
                throw new InvalidInvitationException;
            }

            // Belt and braces. findByToken() located this row *by* the digest
            // of what was presented, so a mismatch here should be impossible —
            // but "should be impossible" is how a lookup that silently changes
            // to a different column becomes an authentication bypass.
            if (! $invitation->tokenMatches($plaintextToken)) {
                throw new InvalidInvitationException;
            }

            $now = now()->toIso8601String();

            try {
                // The aggregate owns single-use and expiry. Both surface as
                // the same opaque failure outward.
                $invitation->accept($now);
            } catch (DomainException) {
                throw new InvalidInvitationException;
            }

            $user = $this->users->findOrFail($invitation->userId);

            // An account that already has a password is not claimable, whatever
            // the token says. Reaching this means an invitation outlived the
            // activation it was issued for, and setting a password here would
            // let an old link overwrite a live credential.
            if ($user->getPasswordHash() !== null) {
                throw new InvalidInvitationException;
            }

            $user->changePassword(PasswordHash::fromPlainPassword($plainPassword));
            $user->activate();

            $this->users->save($user);
            $this->invitations->save($invitation);

            // Recorded for the same reason activation is (ADR-015 PE-7): an
            // account becoming usable is a privilege change, and the audit
            // trail should show when it happened and which invitation carried
            // it. byUserId is the invitee themselves — the one act in the
            // identity model a person performs on their own account.
            event(new InvitationAccepted(
                invitationId: $invitation->id->value,
                userId: $invitation->userId->value,
                invitedBy: $invitation->invitedBy?->value,
                occurredAt: $now,
            ));

            return $user;
        });
    }
}
