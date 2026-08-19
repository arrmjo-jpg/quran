<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Events\CenterDeleted;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;

/**
 * Closes a centre. Soft delete — applications froze its name, and destroying
 * the row would leave those records pointing at nothing.
 *
 * NO CIRCLE GUARD YET, AND THAT IS DELIBERATE RATHER THAN FORGOTTEN. The board
 * settled that a centre holding circles may not be deleted (RESTRICT), but
 * circles do not exist until Story 2 — so today no centre can hold one, and a
 * guard here would be a check nothing could trigger and no test could make
 * fail. ADR-016 called the same situation "closed by absence" for account
 * deletion, and the lesson from honouring it is that the guard must ship in
 * the story that opens the door: Story 2 adds circles and this refusal
 * together, exactly as Epic 1's Story 5 added deletion and its PE-5 guard.
 */
final class DeleteCenterUseCase
{
    public function __construct(
        private CenterRepositoryContract $centers,
    ) {}

    public function execute(string $centerId): void
    {
        DB::transaction(function () use ($centerId): void {
            $id = new CenterId($centerId);
            $center = $this->centers->findOrFail($id);

            $this->centers->delete($id);

            event(new CenterDeleted(
                centerId: $id->value,
                name: $center->getName(),
                occurredAt: now()->toIso8601String(),
            ));
        });
    }
}
