<?php

declare(strict_types=1);

namespace Modules\Organization\Contracts;

/**
 * ResolvedMembershipDTO
 *
 * Cross-module read shape for one period a contestant belonged to a circle,
 * with that circle and its centre already resolved. Owned by Organization.
 *
 * The circle and centre travel nested rather than as bare ids for the reason
 * MembershipResource gives: a membership rendered as two opaque ids is a row
 * nobody can read, and a consumer handed ids would have to come back for
 * each one — which is the N+1 this shape exists to prevent.
 *
 * Only the centre's identifying fields cross: name and city, not its address
 * or coordinates. A contestant's history is not a centre directory, and a
 * consumer needing the full location has the id to read it with.
 *
 * `isActive` is derived here, not left to the consumer. It is `leftAt ===
 * null` — the same condition the unique index uses — and naming it once is
 * what stops three clients each recomputing it and one of them getting it
 * wrong.
 */
final readonly class ResolvedMembershipDTO
{
    public function __construct(
        public string $id,
        public string $contestantId,
        public string $circleId,
        public ?string $circleName,
        public ?string $centerId,
        public ?string $centerName,
        public ?string $centerCity,
        public ?string $joinedAt,
        public ?string $leftAt,
        public bool $isActive,
        public ?string $reason,
    ) {}
}
