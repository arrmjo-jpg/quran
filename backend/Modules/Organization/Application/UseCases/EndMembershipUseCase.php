<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\MembershipId;

/**
 * Ends a membership: the contestant has left the circle.
 *
 * The row stays. Q4 made membership historical precisely so a contestant's
 * past is readable after they move on, and D8's freeze of circle names onto
 * applications assumes those periods remain to be joined against.
 *
 * Every rule here belongs to the aggregate — already ended, reason required,
 * cannot end before it began — so this use case adds none of its own. That is
 * the difference from StartMembershipUseCase, which has to hold a rule no
 * aggregate can see.
 */
final class EndMembershipUseCase
{
    public function __construct(
        private ContestantMembershipRepositoryContract $memberships,
    ) {}

    public function execute(string $membershipId, string $reason, ?string $leftAt = null): void
    {
        DB::transaction(function () use ($membershipId, $reason, $leftAt): void {
            $membership = $this->memberships->findOrFail(new MembershipId($membershipId));

            $membership->end($reason, $leftAt);

            $this->memberships->save($membership);

            foreach ($membership->releaseEvents() as $event) {
                event($event);
            }
        });
    }
}
