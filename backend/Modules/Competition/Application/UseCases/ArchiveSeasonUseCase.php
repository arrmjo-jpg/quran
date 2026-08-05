<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\SeasonStateMachine;

/**
 * ArchiveSeasonUseCase
 *
 * Archives a season that ran its full course (completed -> archived).
 * See CancelSeasonUseCase for ending a season before registration ever
 * opened — the two are deliberately separate use cases because the
 * aggregate records a distinct event for each case.
 */
final readonly class ArchiveSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
        private SeasonStateMachine $stateMachine,
    ) {}

    public function execute(string $seasonId, ?string $reason, ?string $byUserId): Season
    {
        return DB::transaction(function () use ($seasonId, $reason, $byUserId): Season {
            $season = $this->seasons->findOrFail($seasonId);

            $season->archive($this->stateMachine, $reason, $byUserId);

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
