<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Repositories;

use Modules\Organization\Domain\Entities\ContestantMembership;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Modules\Organization\Domain\ValueObjects\MembershipId;

interface ContestantMembershipRepositoryContract
{
    public function find(MembershipId $id): ?ContestantMembership;

    public function findOrFail(MembershipId $id): ContestantMembership;

    /**
     * The contestant's open membership, if they have one.
     *
     * Returns at most one because that is the invariant G1 enforces — but this
     * method is also how the invariant is checked before a second is created,
     * so it reads the condition rather than trusting it.
     */
    public function findActiveForContestant(string $contestantId): ?ContestantMembership;

    /**
     * How many contestants a circle currently holds.
     *
     * Exists for DeleteCircleUseCase's refusal — a rule the Circle aggregate
     * cannot enforce, because it needs a count no aggregate can take of
     * itself. Counts only open memberships: a circle whose contestants have
     * all moved on is a circle nothing depends on any more.
     */
    public function countActiveInCircle(CircleId $circleId): int;

    public function save(ContestantMembership $membership): void;
}
