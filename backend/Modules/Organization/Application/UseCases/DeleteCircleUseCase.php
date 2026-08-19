<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Events\CircleDeleted;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;

/**
 * Closes a circle. Soft delete — applications froze its name (D8), and
 * destroying the row would leave those records pointing at nothing.
 *
 * NO MEMBERSHIP GUARD YET, DELIBERATELY. A circle holding contestants should
 * not vanish under them, but `contestant_memberships` does not exist until
 * Story 3 — so today no circle can hold one, and a guard here would be a check
 * nothing could trigger and no test could make fail. This is the same
 * "closed by absence" reasoning Story 1 recorded for centres, and it carries
 * the same obligation: the guard ships with the story that opens the door.
 */
final class DeleteCircleUseCase
{
    public function __construct(
        private CircleRepositoryContract $circles,
    ) {}

    public function execute(string $circleId): void
    {
        DB::transaction(function () use ($circleId): void {
            $id = new CircleId($circleId);
            $circle = $this->circles->findOrFail($id);

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
