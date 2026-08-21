<?php

declare(strict_types=1);

namespace Modules\Contestants\Application\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Events\ContestantRestored;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\ContestantId;

/**
 * Brings a soft-deleted contestant back — the mirror of
 * DeleteContestantUseCase, and modelled on RestoreUserUseCase.
 *
 * Restoring a live contestant is a no-op rather than an error: the caller
 * asked for a state that already holds. It writes no event, because nothing
 * changed.
 *
 * A contestant that does not exist at all is a different answer — 404 — and
 * the two are kept apart deliberately.
 */
final readonly class RestoreContestantUseCase
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
                throw (new ModelNotFoundException)->setModel(Contestant::class, [$contestantId]);
            }

            if (! $contestant->isDeleted()) {
                return $contestant;
            }

            $this->contestants->restore($id);

            event(new ContestantRestored(
                contestantId: $contestantId,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $this->contestants->findOrFail($id);
        });
    }
}
