<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\SeasonStateMachine;

/**
 * CancelSeasonUseCase
 *
 * Cancels a season before it ever opened for registration (draft ->
 * archived, with a mandatory reason). The state machine itself only
 * allows this from 'draft' — see ArchiveSeasonUseCase for a season that
 * ran its full course.
 */
final readonly class CancelSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
        private SeasonStateMachine $stateMachine,
    ) {}

    public function execute(string $seasonId, string $reason, ?string $byUserId): Season
    {
        return DB::transaction(function () use ($seasonId, $reason, $byUserId): Season {
            $season = $this->seasons->findOrFail($seasonId);

            $season->cancel($this->stateMachine, $reason, $byUserId);

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
