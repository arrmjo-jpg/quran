<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Entities;

use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use Modules\Organization\Domain\Events\MembershipEnded;
use Modules\Organization\Domain\Events\MembershipStarted;
use Modules\Organization\Domain\ValueObjects\MembershipId;

/**
 * A contestant's membership of a circle — ADR-016 Q4.
 *
 * MEMBERSHIP IS A ROW PER PERIOD, NOT A COLUMN ON THE CONTESTANT. Q4 rejected
 * `contestants.circle_id` because it holds only the present and loses every
 * transfer a contestant ever made, which is the history D8 exists to protect.
 * So a contestant who moves twice leaves three rows, and the two closed ones
 * are as real as the open one.
 *
 * NO SOFT DELETES, and that is a decision rather than an omission. A
 * membership is a historical record, not a lifecycle entity like a User or a
 * Circle: it ends by acquiring `leftAt`, and "this membership never happened"
 * is a different claim from "this membership is over". Correcting a mistaken
 * entry is an explicit administrative act, not a hidden row. The practical
 * benefit is that "active" stays one condition — `left_at IS NULL` — instead
 * of two that can disagree.
 *
 * The contestant and circle are held as plain ids. ADR-002 forbids this module
 * importing concrete classes from Contestants, and the id is the whole of what
 * this aggregate needs, exactly as `centerId` is to a Circle.
 *
 * WHAT THIS AGGREGATE CANNOT ENFORCE: that a contestant holds only one active
 * membership. That needs a query across rows no aggregate can take of itself,
 * so it lives in StartMembershipUseCase as a readable refusal and in a unique
 * index as the guarantee — the same division the circle name already uses.
 */
final class ContestantMembership
{
    /** @var array<int, object> */
    private array $events = [];

    private function __construct(
        public readonly MembershipId $id,
        private readonly string $contestantId,
        private readonly string $circleId,
        private readonly string $joinedAt,
        private ?string $leftAt,
        private ?string $reason,
    ) {}

    public static function start(
        MembershipId $id,
        string $contestantId,
        string $circleId,
        ?string $joinedAt = null,
    ): self {
        $joined = self::assertNotFuture($joinedAt);

        $membership = new self(
            $id,
            $contestantId,
            $circleId,
            $joined->toIso8601String(),
            null,
            null,
        );

        $membership->events[] = new MembershipStarted(
            membershipId: $id->value,
            contestantId: $contestantId,
            circleId: $circleId,
            joinedAt: $membership->joinedAt,
            occurredAt: now()->toIso8601String(),
        );

        return $membership;
    }

    /** Rebuilt from storage; records no event. */
    public static function reconstitute(
        MembershipId $id,
        string $contestantId,
        string $circleId,
        string $joinedAt,
        ?string $leftAt,
        ?string $reason,
    ): self {
        return new self($id, $contestantId, $circleId, $joinedAt, $leftAt, $reason);
    }

    public function getContestantId(): string
    {
        return $this->contestantId;
    }

    public function getCircleId(): string
    {
        return $this->circleId;
    }

    public function getJoinedAt(): string
    {
        return $this->joinedAt;
    }

    public function getLeftAt(): ?string
    {
        return $this->leftAt;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isActive(): bool
    {
        return $this->leftAt === null;
    }

    /**
     * Ends the membership.
     *
     * The reason is required rather than optional. A closed membership with no
     * explanation is the row an administrator finds a year later and cannot
     * act on: it says a contestant left and nothing about whether they moved
     * centre, stopped attending, or were entered by mistake. Q4 lists `reason`
     * alongside the dates for exactly this.
     *
     * Ending an already-ended membership is refused rather than treated as
     * idempotent. Two different closing dates for one period is a correction,
     * and a correction should be visible rather than silently applied.
     */
    public function end(string $reason, ?string $leftAt = null): void
    {
        if ($this->leftAt !== null) {
            throw new DomainException('This membership has already ended.');
        }

        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw new InvalidArgumentException('A reason is required when ending a membership.');
        }

        if (mb_strlen($trimmedReason) > 500) {
            throw new InvalidArgumentException('A reason cannot exceed 500 characters.');
        }

        $left = self::assertNotFuture($leftAt);

        if ($left->lessThan(CarbonImmutable::parse($this->joinedAt))) {
            throw new InvalidArgumentException('A membership cannot end before it began.');
        }

        $this->leftAt = $left->toIso8601String();
        $this->reason = $trimmedReason;

        $this->events[] = new MembershipEnded(
            membershipId: $this->id->value,
            contestantId: $this->contestantId,
            circleId: $this->circleId,
            leftAt: $this->leftAt,
            reason: $this->reason,
            occurredAt: now()->toIso8601String(),
        );
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * Dates are accepted rather than always taken from the clock, because
     * enrolment is recorded after the fact more often than as it happens. A
     * future date is refused: it would make a membership active before it
     * exists, and the one-active-membership rule counts rows, not calendars.
     */
    private static function assertNotFuture(?string $iso): CarbonImmutable
    {
        if ($iso === null) {
            return CarbonImmutable::now();
        }

        try {
            $parsed = CarbonImmutable::parse($iso);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Not a usable date: {$iso}");
        }

        if ($parsed->isFuture()) {
            throw new InvalidArgumentException('A membership date cannot be in the future.');
        }

        return $parsed;
    }
}
