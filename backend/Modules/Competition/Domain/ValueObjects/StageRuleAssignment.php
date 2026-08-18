<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * StageRuleAssignment
 *
 * One stage's judging rule as submitted by an admin — the write-side
 * counterpart of ResolvedStageRule, which is the already-resolved read
 * model built for the rule snapshot. This one carries ids only; nothing
 * is looked up yet.
 *
 * qualification_percentage is nullable on purpose: a final stage may rank
 * without eliminating anyone. The 0..100 bound is enforced here rather
 * than left to the database, because chk_season_stage_rules_qualification_pct
 * is a MySQL-only CHECK constraint — on any other driver an out-of-range
 * value would otherwise be stored silently.
 */
final readonly class StageRuleAssignment
{
    public function __construct(
        public string $stageId,
        public string $judgeScoreSystemId,
        public ?float $qualificationPercentage = null,
    ) {
        if ($qualificationPercentage !== null && ($qualificationPercentage < 0.0 || $qualificationPercentage > 100.0)) {
            throw new InvalidArgumentException(
                "qualification_percentage must be between 0 and 100, got {$qualificationPercentage}."
            );
        }
    }
}
