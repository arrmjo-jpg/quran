<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Exceptions\SeasonNotRestorableException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;

/**
 * RestoreSeasonUseCase
 *
 * Returns an archived season to `draft`, and only when the archival was a
 * mistake rather than a record of a season that actually ran.
 *
 * Restoring is not the inverse of archiving. `archived` stays terminal for
 * any season that opened, froze, or that anything came to depend on — the
 * whole point of the state being terminal is that the record is permanent.
 * What this covers is the one case where nothing real happened: a draft
 * season cancelled by accident, which never opened and which nothing
 * references.
 *
 * The guards are split by who can see what. Season::restore() enforces the
 * three the aggregate knows about — it is archived, it never froze, it does
 * not hold the single active slot. The rest live here, because they are
 * about rows in other tables and an aggregate has no business querying
 * those:
 *
 *   season_rule_versions  a frozen snapshot means the season really opened
 *   applications          somebody entered
 *   streams               a broadcast was scheduled against it
 *   stage_results         judging produced results
 *   judge_assignments     judges were assigned to its stages
 *
 * Note the season's own configuration — stages, stage rules, eligible
 * countries — is deliberately NOT a blocker. That is the season's own
 * setup, and a restored draft is expected to still have it.
 */
final readonly class RestoreSeasonUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
    ) {}

    public function execute(string $seasonId, ?string $byUserId = null): Season
    {
        return DB::transaction(function () use ($seasonId, $byUserId): Season {
            $season = $this->seasons->findOrFail($seasonId);

            // Checked before touching the aggregate: a blocker means the
            // season genuinely ran, which is a clearer thing to report
            // than whatever the aggregate would complain about next.
            $blockers = $this->seasons->findRestoreBlockers($seasonId);

            if ($blockers !== []) {
                throw new SeasonNotRestorableException(
                    seasonId: $seasonId,
                    reason: $this->reasonFor($blockers),
                    details: $this->describe($blockers),
                );
            }

            $season->restore($byUserId);

            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                event($event);
            }

            return $season;
        });
    }

    /**
     * A rule snapshot gets its own code because it is the strongest signal
     * that the season really opened — the others could in principle be
     * stray test data, that one cannot.
     *
     * @param  array<string, int>  $blockers
     */
    private function reasonFor(array $blockers): string
    {
        return isset($blockers['season_rule_versions'])
            ? SeasonNotRestorableException::HAS_SNAPSHOTS
            : SeasonNotRestorableException::HAS_DEPENDENTS;
    }

    /**
     * @param  array<string, int>  $blockers
     * @return array<int, string>
     */
    private function describe(array $blockers): array
    {
        $details = [];

        foreach ($blockers as $table => $count) {
            $details[] = "{$table}: {$count}";
        }

        return $details;
    }
}
