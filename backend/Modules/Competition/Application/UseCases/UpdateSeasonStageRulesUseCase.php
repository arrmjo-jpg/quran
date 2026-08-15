<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;
use Modules\Competition\Domain\ValueObjects\StageRuleAssignment;

/**
 * UpdateSeasonStageRulesUseCase
 *
 * Sets a season's per-stage judging rules as one complete set: the whole
 * array in, the previous rows out, inside a single transaction. There is
 * no add-one/remove-one surface by design — a half-configured rule set is
 * never a valid state for a season, so it is never a reachable one.
 *
 * The completeness check is the point of this Use Case. season_stage_rules
 * is what ResolvedSeasonRules feeds on when a season freezes, and a season
 * with three stages but two rules would previously have frozen quite
 * happily, snapshotting a rule set that silently omitted a stage. Every
 * stage of the season must appear exactly once: no gaps, no duplicates,
 * and no stage belonging to some other season.
 *
 * A frozen season is refused for the same reason its other rules are —
 * the configuration entrants signed up under must not move.
 */
final readonly class UpdateSeasonStageRulesUseCase
{
    public function __construct(
        private SeasonRepositoryContract $seasons,
        private StageRepositoryContract $stages,
        private SeasonStageRuleRepositoryContract $stageRules,
    ) {}

    /**
     * @param  array<int, StageRuleAssignment>  $assignments
     * @return array<int, ResolvedStageRule>
     */
    public function execute(string $seasonId, array $assignments): array
    {
        return DB::transaction(function () use ($seasonId, $assignments): array {
            $this->seasons->findOrFail($seasonId)->assertMutable('stage_rules');

            $this->assertCoversEveryStage($seasonId, $assignments);

            $this->stageRules->replaceAll($seasonId, array_values($assignments));

            return $this->stageRules->findResolvedRules($seasonId);
        });
    }

    /**
     * @param  array<int, StageRuleAssignment>  $assignments
     */
    private function assertCoversEveryStage(string $seasonId, array $assignments): void
    {
        $seasonStageIds = array_map(
            static fn (Stage $stage): string => $stage->id,
            $this->stages->findBySeason($seasonId)
        );

        if ($seasonStageIds === []) {
            throw new IncompleteSeasonRulesException(['stages']);
        }

        $submittedStageIds = array_map(
            static fn (StageRuleAssignment $assignment): string => $assignment->stageId,
            $assignments
        );

        $duplicates = array_unique(array_diff_assoc($submittedStageIds, array_unique($submittedStageIds)));
        if ($duplicates !== []) {
            throw new IncompleteSeasonRulesException(
                array_map(static fn (string $id): string => "stage_rule.duplicate.{$id}", array_values($duplicates))
            );
        }

        $missing = array_diff($seasonStageIds, $submittedStageIds);
        $unknown = array_diff($submittedStageIds, $seasonStageIds);

        $problems = [
            ...array_map(static fn (string $id): string => "stage_rule.missing.{$id}", array_values($missing)),
            ...array_map(static fn (string $id): string => "stage_rule.not_in_season.{$id}", array_values($unknown)),
        ];

        if ($problems !== []) {
            throw new IncompleteSeasonRulesException($problems);
        }
    }
}
