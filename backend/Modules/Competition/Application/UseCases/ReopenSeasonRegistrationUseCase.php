<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Exceptions\SeasonNotReopenableException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

/**
 * ReopenSeasonRegistrationUseCase
 *
 * Puts a closed registration window back open, for the case where
 * organisers decide entrants should still be able to apply. Legal only
 * from `registration_closed`, with a mandatory reason.
 *
 * Nothing is unfrozen and no new rule snapshot is written: the season's
 * rules stay exactly as entrants first saw them, and version 1 remains the
 * record. Only the window moves.
 *
 * Concurrency (ADR-005 Decision 16): reopening reclaims the single active
 * slot, which is the same contended resource openRegistration competes
 * for, so it resolves the season through findOrFailForActivation() — that
 * locks every season row before anything is read. Without the lock two
 * admins reopening different seasons at once could both see "no other
 * season is active" and race each other to the uk_seasons_single_active
 * constraint.
 *
 * If another season already holds the slot, this refuses rather than
 * quietly taking it. openRegistration deactivates the previous holder
 * because that is a season starting its life; reopening is a correction,
 * and silently closing a running season's registration to make room is not
 * something an admin asked for.
 */
final readonly class ReopenSeasonRegistrationUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    public function execute(string $seasonId, string $reason, ?string $byUserId = null): Season
    {
        return DB::transaction(function () use ($seasonId, $reason, $byUserId): Season {
            $season = $this->seasons->findOrFailForActivation($seasonId);

            $active = $this->seasons->findActiveSeason();

            if ($active !== null && $active->id !== $seasonId) {
                throw new SeasonNotReopenableException(
                    seasonId: $seasonId,
                    reason: SeasonNotReopenableException::ANOTHER_SEASON_ACTIVE,
                    activeSeasonId: $active->id,
                    activeSeasonSlug: $active->getSlug(),
                    activeSeasonYear: $active->getYear(),
                    message: sprintf(
                        'Season %s cannot reopen registration: season %s (%d) is currently the active season.',
                        $seasonId,
                        $active->getSlug(),
                        $active->getYear(),
                    ),
                );
            }

            $season->reopenRegistration($reason, $byUserId);

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }
}
