<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/** A contestant joined a circle. */
final readonly class MembershipStarted
{
    public const TYPE = 'membership_started';

    public function __construct(
        public string $membershipId,
        public string $contestantId,
        public string $circleId,
        public string $joinedAt,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'contestant_id' => $this->contestantId,
            'circle_id' => $this->circleId,
            'joined_at' => $this->joinedAt,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
