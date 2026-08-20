<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/**
 * A contestant moved from one circle to another.
 *
 * Emitted in addition to the MembershipEnded and MembershipStarted pair the
 * move produces, not instead of them. Those two say what happened to each
 * membership row; this says the two are one act. A consumer reconstructing a
 * transfer from an ended membership followed by a started one has to guess at
 * the join, and would guess wrong for a contestant who genuinely left and
 * rejoined a different circle a month later.
 *
 * Raised by TransferMembershipUseCase rather than by an aggregate, because it
 * spans two of them and no single membership can observe the pair.
 */
final readonly class ContestantTransferred
{
    public const TYPE = 'contestant_transferred';

    public function __construct(
        public string $contestantId,
        public string $fromCircleId,
        public string $toCircleId,
        public string $endedMembershipId,
        public string $startedMembershipId,
        public string $reason,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'contestant_id' => $this->contestantId,
            'from_circle_id' => $this->fromCircleId,
            'to_circle_id' => $this->toCircleId,
            'ended_membership_id' => $this->endedMembershipId,
            'started_membership_id' => $this->startedMembershipId,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
