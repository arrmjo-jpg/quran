<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Events\SeasonRulesFrozen;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Repositories\ParticipationTypeRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonCountryRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonRuleVersionRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\Repositories\TajweedLevelRepositoryContract;
use Modules\Competition\Domain\Services\SeasonRuleSnapshotFactory;
use Modules\Competition\Domain\Services\SeasonStateMachine;
use Modules\Competition\Domain\ValueObjects\ResolvedSeasonRules;

/**
 * OpenSeasonRegistrationUseCase
 *
 * The draft -> registration_open transition, atomically: resolve every
 * lookup the season references, freeze the aggregate (validates
 * completeness, transitions state, builds the rule snapshot in memory),
 * persist the season row + the season_rule_versions row, then release and
 * dispatch the domain events the aggregate recorded — all inside one
 * DB::transaction. Any failure at any step rolls the whole thing back; no
 * step here is a repository or Controller concern.
 *
 * This is also the single collection point for the events freeze()
 * records — Controllers never touch them, and events are never lost:
 * every released event is dispatched via event(), the seam a future
 * Outbox consumer would hook into without needing this method to change.
 *
 * Concurrency (ADR-005 Decision 16): resolving the season via
 * findOrFailForActivation() takes a pessimistic lock across every season
 * row before anything is read, so a second, concurrent activation
 * attempt — of this season or any other draft season — blocks on that
 * lock instead of racing this transaction to the uk_seasons_single_active
 * constraint.
 */
final readonly class OpenSeasonRegistrationUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
        private ParticipationTypeRepositoryContract $participationTypes,
        private TajweedLevelRepositoryContract $tajweedLevels,
        private SeasonCountryRepositoryContract $seasonCountries,
        private SeasonStageRuleRepositoryContract $seasonStageRules,
        private SeasonRuleVersionRepositoryContract $ruleVersions,
        private SeasonStateMachine $stateMachine,
        private SeasonRuleSnapshotFactory $snapshotFactory,
    ) {}

    public function execute(string $seasonId, ?string $performedByUserId = null): Season
    {
        return DB::transaction(function () use ($seasonId, $performedByUserId): Season {
            $season = $this->seasons->findOrFailForActivation($seasonId);

            $rules = $this->resolveRules($season);

            $season->freeze($this->stateMachine, $this->snapshotFactory, $rules);

            // deactivateOthers() MUST run before save(): seasons.active_flag
            // is a generated column with a unique index (uk_seasons_single_
            // active), so if a previously active season is still is_active=1
            // when this save() sets the new season's is_active=1 too, MySQL
            // rejects it — two rows briefly claiming the one active slot,
            // even though only this transaction is touching the table.
            // Clearing the old holder first means the target row is the
            // only one ever claiming active_flag=1.
            $this->seasons->deactivateOthers($season->id);
            $this->seasons->save($season);

            foreach ($season->releaseEvents() as $event) {
                if ($event instanceof SeasonRulesFrozen) {
                    $this->ruleVersions->save($event->seasonId, $event->version, $event->snapshot, $performedByUserId);
                }

                event($event);
            }

            return $season;
        });
    }

    /**
     * Resolving a lookup by a null id isn't meaningful (it would surface
     * as a confusing "model not found" error rather than a clear
     * completeness one), so this mirrors Season::assertRulesSelected()'s
     * presence check before spending any repository I/O — freeze() still
     * re-validates independently once ResolvedSeasonRules exists; this is
     * a precondition for resolution, not a substitute for that guard.
     */
    private function resolveRules(Season $season): ResolvedSeasonRules
    {
        $participationTypeId = $season->getParticipationTypeId();
        $tajweedLevelId = $season->getTajweedLevelId();

        $missing = [];

        if ($season->getMinAge() === null || $season->getMaxAge() === null) {
            $missing[] = 'age_range';
        }

        if ($participationTypeId === null) {
            $missing[] = 'participation_type_id';
        }

        if ($tajweedLevelId === null) {
            $missing[] = 'tajweed_level_id';
        }

        if ($missing !== []) {
            throw new IncompleteSeasonRulesException($missing);
        }

        return new ResolvedSeasonRules(
            participationType: $this->participationTypes->findOrFail($participationTypeId),
            tajweedLevel: $this->tajweedLevels->findOrFail($tajweedLevelId),
            eligibleCountries: $this->seasonCountries->findEligibleCountries($season->id),
            stageRules: $this->seasonStageRules->findResolvedRules($season->id),
        );
    }
}
