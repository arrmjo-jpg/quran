<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * A pending account was claimed by its invitee — ADR-016 D14.
 *
 * An account becoming usable is a privilege change, so PE-7 applies to it as
 * much as to a role grant. Carries the invitation it was redeemed against and
 * who issued that invitation, because "who let this person in" is the question
 * an auditor asks and it cannot be reconstructed later once the invitation is
 * just another accepted row.
 *
 * There is no byUserId. The invitee acts on their own account here — the one
 * place in the identity model where that is not only allowed but required.
 */
final readonly class InvitationAccepted
{
    public const TYPE = 'invitation_accepted';

    public function __construct(
        public string $invitationId,
        public string $userId,
        public ?string $invitedBy,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'invitation_id' => $this->invitationId,
            'user_id' => $this->userId,
            'invited_by' => $this->invitedBy,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
