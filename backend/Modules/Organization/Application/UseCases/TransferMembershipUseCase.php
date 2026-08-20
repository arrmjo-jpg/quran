<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\ContestantMembership;
use Modules\Organization\Domain\Events\ContestantTransferred;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Modules\Organization\Domain\ValueObjects\MembershipId;

/**
 * Moves a contestant from one circle to another.
 *
 * ONE TRANSACTION, NOT TWO CALLS. Ending and starting separately would leave a
 * window in which the contestant belongs to no circle, and — because Story 4
 * will make an active membership a precondition for applying — an application
 * submitted in that window would be refused for a contestant who is mid-move
 * rather than genuinely unenrolled. Wrapping both is also what lets the unique
 * index stay strict: the old row acquires `left_at` before the new one is
 * inserted, so the two never both count as open.
 *
 * ORDER MATTERS AND IS LOAD-BEARING. The end must be written before the start,
 * or the index refuses the insert. That is the index doing its job rather than
 * an obstacle: a transfer that could write the new row first is a transfer
 * that could leave two open memberships if it failed halfway.
 *
 * A transfer emits ContestantTransferred alongside the two membership events,
 * because the pair alone cannot be told apart from a departure followed a
 * month later by an unrelated enrolment.
 */
final class TransferMembershipUseCase
{
    public function __construct(
        private ContestantMembershipRepositoryContract $memberships,
        private CircleRepositoryContract $circles,
    ) {}

    public function execute(
        string $contestantId,
        string $toCircleId,
        string $reason,
        ?string $at = null,
    ): ContestantMembership {
        return DB::transaction(function () use ($contestantId, $toCircleId, $reason, $at): ContestantMembership {
            $this->circles->findOrFail(new CircleId($toCircleId));

            $current = $this->memberships->findActiveForContestant($contestantId);

            if ($current === null) {
                // Refused rather than treated as a plain enrolment. "Transfer"
                // asserts the contestant is somewhere already, and silently
                // turning it into a first enrolment would hide a mistaken
                // contestant id behind a successful-looking response.
                throw new DomainException(
                    'This contestant has no active membership to transfer. Enrol them instead.'
                );
            }

            if ($current->getCircleId() === $toCircleId) {
                throw new DomainException('This contestant is already in that circle.');
            }

            $fromCircleId = $current->getCircleId();

            $current->end($reason, $at);
            $this->memberships->save($current);

            $next = ContestantMembership::start(
                id: MembershipId::generate(),
                contestantId: $contestantId,
                circleId: $toCircleId,
                joinedAt: $at,
            );

            $this->memberships->save($next);

            foreach ([...$current->releaseEvents(), ...$next->releaseEvents()] as $event) {
                event($event);
            }

            event(new ContestantTransferred(
                contestantId: $contestantId,
                fromCircleId: $fromCircleId,
                toCircleId: $toCircleId,
                endedMembershipId: $current->id->value,
                startedMembershipId: $next->id->value,
                reason: $current->getReason() ?? $reason,
                occurredAt: now()->toIso8601String(),
            ));

            return $next;
        });
    }
}
