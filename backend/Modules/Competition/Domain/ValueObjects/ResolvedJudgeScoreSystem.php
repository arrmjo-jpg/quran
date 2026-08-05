<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

/**
 * ResolvedJudgeScoreSystem
 *
 * A single already-fetched judge_score_systems row, carrying its scale
 * ceiling alongside the usual lookup id/code/name — ResolvedStageRule
 * needs max_score to derive required_score, which plain
 * ResolvedLookupOption doesn't carry.
 */
final readonly class ResolvedJudgeScoreSystem
{
    /**
     * @param  array<string, string>  $name  locale => translated name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public float $maxScore,
    ) {}

    public function toLookupOption(): ResolvedLookupOption
    {
        return new ResolvedLookupOption($this->id, $this->code, $this->name);
    }
}
