<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Events\CenterDeleted;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;

/**
 * Closes a centre. Soft delete — applications froze its name, and destroying
 * the row would leave those records pointing at nothing.
 *
 * A CENTRE HOLDING CIRCLES MAY NOT BE CLOSED. Story 1 left this guard out on
 * purpose and said why: circles did not exist, so the check could not have
 * been triggered and no test could have made it fail. Story 2 opens that door
 * and closes it in the same change, which is the obligation Story 1 recorded.
 *
 * The refusal lives here rather than in the Center aggregate because it needs
 * a count no aggregate can take of itself, and rather than in the database
 * because the schema's RESTRICT never fires: both rows are soft deleted, so
 * closing a centre is an UPDATE the constraint never sees. The foreign key
 * still earns its place against hard deletes and direct SQL — it is the
 * backstop, not the mechanism.
 */
final class DeleteCenterUseCase
{
    public function __construct(
        private CenterRepositoryContract $centers,
        private CircleRepositoryContract $circles,
    ) {}

    public function execute(string $centerId): void
    {
        DB::transaction(function () use ($centerId): void {
            $id = new CenterId($centerId);
            $center = $this->centers->findOrFail($id);

            $held = $this->circles->countInCenter($id);

            if ($held > 0) {
                // The count is in the message on purpose. "This centre still
                // holds circles" tells an operator nothing about how much work
                // closing it involves, and they cannot see the circles from the
                // screen that refused them.
                throw new DomainException(
                    "This centre still holds {$held} circle(s). Close or move them before closing the centre."
                );
            }

            $this->centers->delete($id);

            event(new CenterDeleted(
                centerId: $id->value,
                name: $center->getName(),
                occurredAt: now()->toIso8601String(),
            ));
        });
    }
}
