<?php

declare(strict_types=1);

namespace Modules\Contestants\Application\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Events\ContestantDeleted;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\ContestantId;

/**
 * Takes a contestant out of service without destroying the record —
 * ADR-016 D5, soft delete only.
 *
 * Soft is not a preference here. `applications`, `contestant_memberships` and
 * `evaluations` all reference this row with RESTRICT: a hard delete would
 * either be refused by the database or, if the references were cleared first,
 * take a person's competition history with it.
 *
 * Deleting an already-deleted contestant is a no-op that records nothing,
 * matching RestoreUserUseCase's treatment of the mirror case.
 */
final readonly class DeleteContestantUseCase
{
    public function __construct(
        private ContestantRepositoryContract $contestants,
    ) {}

    public function execute(string $contestantId, ?string $byUserId = null): Contestant
    {
        return DB::transaction(function () use ($contestantId, $byUserId): Contestant {
            $id = new ContestantId($contestantId);
            $contestant = $this->contestants->findWithTrashed($id);

            if ($contestant === null) {
                throw (new ModelNotFoundException)
                    ->setModel(Contestant::class, [$contestantId]);
            }

            if ($contestant->isDeleted()) {
                return $contestant;
            }

            $this->contestants->delete($id);

            event(new ContestantDeleted(
                contestantId: $contestantId,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $contestant;
        });
    }
}
