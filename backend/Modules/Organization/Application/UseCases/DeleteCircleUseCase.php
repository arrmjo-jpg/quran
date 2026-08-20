<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Events\CircleDeleted;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;

/**
 * Closes a circle. Soft delete — applications froze its name (D8), and
 * destroying the row would leave those records pointing at nothing.
 *
 * A CIRCLE HOLDING CONTESTANTS MAY NOT BE CLOSED. Story 2 left this guard out
 * on purpose and said why: `contestant_memberships` did not exist, so the
 * check could not have been triggered and no test could have made it fail.
 * Story 3 opens that door and closes it in the same change, which is the
 * obligation Story 2 recorded — and the second time this module has honoured
 * it, after Story 2 did the same for centres.
 *
 * Only OPEN memberships count. A circle whose contestants have all moved on
 * still holds their history, and that history is exactly what soft deletion
 * preserves; refusing to close it would mean no circle could ever be closed
 * once anyone had passed through it.
 *
 * The refusal lives here rather than in the database because the schema's
 * RESTRICT never fires: circles are soft deleted, which is an UPDATE the
 * constraint never sees. The foreign key still earns its place against hard
 * deletes and direct SQL — it is the backstop, not the mechanism.
 */
final class DeleteCircleUseCase
{
    public function __construct(
        private CircleRepositoryContract $circles,
        private ContestantMembershipRepositoryContract $memberships,
    ) {}

    public function execute(string $circleId): void
    {
        DB::transaction(function () use ($circleId): void {
            $id = new CircleId($circleId);
            $circle = $this->circles->findOrFail($id);

            $held = $this->memberships->countActiveInCircle($id);

            if ($held > 0) {
                // The count is in the message for the reason DeleteCenterUseCase
                // gives: "this circle still has contestants" tells an operator
                // nothing about how much work closing it involves, and they
                // cannot see those contestants from the screen that refused them.
                throw new DomainException(
                    "This circle still has {$held} enrolled contestant(s). "
                    .'Transfer or unenrol them before closing the circle.'
                );
            }

            $this->circles->delete($id);

            event(new CircleDeleted(
                circleId: $id->value,
                name: $circle->getName(),
                centerId: $circle->getCenterId(),
                occurredAt: now()->toIso8601String(),
            ));
        });
    }
}
