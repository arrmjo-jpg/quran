<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * ResolvedStageRule
 *
 * A single stage's already-fetched judging configuration: its display
 * name, which score system it's judged on, and its qualification
 * percentage (null means no elimination threshold applies — e.g. a
 * final stage that only ranks). required_score is derived here, once,
 * from judgeScoreSystem.max_score * qualification_percentage — never
 * left as a hardcoded absolute threshold elsewhere in the codebase.
 */
final readonly class ResolvedStageRule
{
    public float $maxScore;

    public ?float $requiredScore;

    /**
     * @param  array<string, string>  $name  locale => translated stage name
     */
    public function __construct(
        public string $stageId,
        public int $stageNumber,
        public string $type,
        public array $name,
        public ResolvedLookupOption $judgeScoreSystem,
        float $judgeScoreSystemMaxScore,
        public ?float $qualificationPercentage,
    ) {
        if ($qualificationPercentage !== null && ($qualificationPercentage < 0.0 || $qualificationPercentage > 100.0)) {
            throw new InvalidArgumentException("qualification_percentage must be between 0 and 100, got {$qualificationPercentage}");
        }

        $this->maxScore = $judgeScoreSystemMaxScore;
        $this->requiredScore = $qualificationPercentage === null
            ? null
            : round($judgeScoreSystemMaxScore * ($qualificationPercentage / 100), 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage_number' => $this->stageNumber,
            'type' => $this->type,
            'name' => $this->name,
            'judge_score_system' => [
                'code' => $this->judgeScoreSystem->code,
                'max_score' => $this->maxScore,
            ],
            'qualification_percentage' => $this->qualificationPercentage,
            'required_score' => $this->requiredScore,
        ];
    }
}
