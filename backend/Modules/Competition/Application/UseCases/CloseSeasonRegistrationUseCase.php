<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

/**
 * CloseSeasonRegistrationUseCase
 *
 * The registration_open -> registration_closed transition. Same shape as
 * ArchiveSeasonUseCase/CancelSeasonUseCase: resolve, mutate, persist,
 * release + dispatch events, all inside one DB::transaction — the
 * Controller previously called Season::closeRegistration() +
 * repository->save() directly and never touched releaseEvents(), so
 * SeasonRegistrationClosed was silently dropped.
 *
 * closeRegistration() itself still throws the original
 * \InvalidArgumentException on an invalid transition (it predates the
 * InvalidSeasonTransitionException introduced for archive()/cancel()) —
 * left as-is, unrelated to this fix.
 */
final readonly class CloseSeasonRegistrationUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    public function execute(string $seasonId): Season
    {
        return DB::transaction(function () use ($seasonId): Season {
            $season = $this->seasons->findOrFail($seasonId);

            $season->closeRegistration();

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
