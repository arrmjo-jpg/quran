<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;
use Modules\Competition\Domain\ValueObjects\StageRuleAssignment;

interface SeasonStageRuleRepositoryContract
{
    /**
     * A season's per-stage judging rules (score system + qualification
     * percentage), resolved and ordered by stage_number. Backs
     * ResolvedSeasonRules::$stageRules.
     *
     * @return array<int, ResolvedStageRule>
     */
    public function findResolvedRules(string $seasonId): array;

    /**
     * Replace a season's entire rule set: drop what is stored and write
     * the submitted set in its place. There is deliberately no add-one or
     * delete-one counterpart — a season's stage rules are only ever
     * meaningful as a complete set (every stage covered, exactly once),
     * so they are only ever written as one.
     *
     * Callers must already have validated the set and must wrap this in a
     * transaction; the delete leaves the season ruleless in between.
     *
     * @param  array<int, StageRuleAssignment>  $assignments
     */
    public function replaceAll(string $seasonId, array $assignments): void;
}
