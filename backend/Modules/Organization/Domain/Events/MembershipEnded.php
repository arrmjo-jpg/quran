<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

/**
 * A contestant's membership of a circle ended.
 *
 * Carries the reason, because a consumer reading only "left" cannot tell a
 * transfer from a departure — and those call for different responses.
 */
final readonly class MembershipEnded
{
    public const TYPE = 'membership_ended';

    public function __construct(
        public string $membershipId,
        public string $contestantId,
        public string $circleId,
        public string $leftAt,
        public string $reason,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'contestant_id' => $this->contestantId,
            'circle_id' => $this->circleId,
            'left_at' => $this->leftAt,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
