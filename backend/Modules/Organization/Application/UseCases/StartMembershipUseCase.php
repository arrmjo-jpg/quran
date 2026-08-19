<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\ContestantMembership;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Modules\Organization\Domain\ValueObjects\MembershipId;

/**
 * Enrols a contestant in a circle.
 *
 * G1 — ONE ACTIVE MEMBERSHIP PER CONTESTANT — IS ENFORCED HERE AND IN A UNIQUE
 * INDEX, and the two are not redundant. This check reads the contestant's open
 * membership and names the circle they are already in, which is what an
 * operator needs to decide between transferring them and abandoning the
 * enrolment. The index cannot say that; it can only refuse. But this check
 * cannot survive two requests arriving together, and the index can. Each does
 * what the other cannot, so removing either leaves a real gap.
 *
 * The rule is not in the aggregate because no aggregate can see it: a
 * membership knows whether IT is open, not whether a sibling row is. That is
 * the same reason DeleteCenterUseCase holds the circle count rather than the
 * Center entity holding it.
 */
final class StartMembershipUseCase
{
    public function __construct(
        private ContestantMembershipRepositoryContract $memberships,
        private CircleRepositoryContract $circles,
    ) {}

    public function execute(
        string $contestantId,
        string $circleId,
        ?string $joinedAt = null,
    ): ContestantMembership {
        return DB::transaction(function () use ($contestantId, $circleId, $joinedAt): ContestantMembership {
            // Resolved rather than assumed. The request rule proves a row with
            // this id exists; this proves it is still there inside the
            // transaction, and gives a domain-shaped failure if it is not.
            $this->circles->findOrFail(new CircleId($circleId));

            $open = $this->memberships->findActiveForContestant($contestantId);

            if ($open !== null) {
                // The circle is in the message on purpose. "Already enrolled"
                // leaves the operator to go looking; naming the circle lets
                // them transfer instead, which is almost always what they
                // meant.
                throw new DomainException(
                    "This contestant is already enrolled in circle {$open->getCircleId()}. "
                    .'End that membership or transfer them instead.'
                );
            }

            $membership = ContestantMembership::start(
                id: MembershipId::generate(),
                contestantId: $contestantId,
                circleId: $circleId,
                joinedAt: $joinedAt,
            );

            $this->memberships->save($membership);

            foreach ($membership->releaseEvents() as $event) {
                event($event);
            }

            return $membership;
        });
    }
}
