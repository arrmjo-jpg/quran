<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use DomainException;
use Modules\Core\Domain\ValueObjects\InvitationId;
use Modules\Core\Domain\ValueObjects\InvitationToken;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * An outstanding claim on a pending account — ADR-016 D14.
 *
 * The aggregate owns the two questions that decide whether a token may be
 * redeemed, and it owns them so that no caller can answer them differently:
 * has it already been used, and has it run out of time. Both are asked inside
 * accept(), not left to whoever holds the object, because a check performed at
 * the call site is a check that can be omitted at the next call site.
 *
 * WHAT THIS AGGREGATE DELIBERATELY DOES NOT DO: it does not set a password, it
 * does not activate the account, and it does not touch roles. Those belong to
 * the User aggregate, and an invitation that could reach into a user would put
 * account state in two places. accept() answers one question — "is this
 * redemption legitimate?" — and records that it happened. The use case
 * orchestrates the rest, which is also where the two writes can share a
 * transaction.
 */
final class Invitation
{
    private function __construct(
        public readonly InvitationId $id,
        public readonly UserId $userId,
        private InvitationToken $token,
        private readonly string $expiresAt,
        private ?string $acceptedAt,
        public readonly ?UserId $invitedBy,
    ) {}

    /**
     * A fresh invitation. The returned token carries the only copy of the
     * plaintext that will ever exist.
     */
    public static function issue(
        UserId $userId,
        InvitationToken $token,
        string $expiresAt,
        ?UserId $invitedBy = null,
    ): self {
        return new self(
            id: InvitationId::generate(),
            userId: $userId,
            token: $token,
            expiresAt: $expiresAt,
            acceptedAt: null,
            invitedBy: $invitedBy,
        );
    }

    /** Rebuilt from storage by the repository. */
    public static function reconstitute(
        InvitationId $id,
        UserId $userId,
        InvitationToken $token,
        string $expiresAt,
        ?string $acceptedAt,
        ?UserId $invitedBy,
    ): self {
        return new self($id, $userId, $token, $expiresAt, $acceptedAt, $invitedBy);
    }

    public function tokenHash(): string
    {
        return $this->token->hash;
    }

    public function expiresAt(): string
    {
        return $this->expiresAt;
    }

    public function acceptedAt(): ?string
    {
        return $this->acceptedAt;
    }

    public function isAccepted(): bool
    {
        return $this->acceptedAt !== null;
    }

    /**
     * Expiry is evaluated against a time the caller supplies rather than read
     * from a clock in here. A domain object that reads the wall clock cannot
     * be tested at a boundary without freezing global time, and the boundary
     * is the only part of expiry worth testing.
     */
    public function isExpired(string $now): bool
    {
        return strtotime($now) >= strtotime($this->expiresAt);
    }

    public function isRedeemable(string $now): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired($now);
    }

    /**
     * Marks this invitation used. Single-use is enforced here rather than by a
     * unique index or a caller's check, because "already accepted" and "never
     * existed" must be answerable as different things.
     *
     * @throws DomainException when already accepted or past its expiry
     */
    public function accept(string $now): void
    {
        if ($this->isAccepted()) {
            throw new DomainException('This invitation has already been accepted.');
        }

        if ($this->isExpired($now)) {
            throw new DomainException('This invitation has expired.');
        }

        $this->acceptedAt = $now;
    }

    /**
     * Whether a presented plaintext is this invitation's token.
     *
     * Says nothing about whether it may be redeemed — a matching token on an
     * expired invitation still matches. Keeping the two apart means a caller
     * cannot accidentally treat "wrong token" and "too late" as the same
     * outcome, which are different things to tell a person.
     */
    public function tokenMatches(string $plaintext): bool
    {
        return $this->token->matches($plaintext);
    }
}
