<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;

/**
 * Wraps the resolved read model, so the response carries the derived
 * required_score alongside the percentage that produced it rather than
 * making every client recompute max_score * percentage itself.
 */
final class StageRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ResolvedStageRule $rule */
        $rule = $this->resource;

        return [
            'stage_id' => $rule->stageId,
            'stage_number' => $rule->stageNumber,
            'type' => $rule->type,
            'name' => $rule->name,
            'judge_score_system' => [
                'id' => $rule->judgeScoreSystem->id,
                'code' => $rule->judgeScoreSystem->code,
                'name' => $rule->judgeScoreSystem->name,
                'max_score' => $rule->maxScore,
            ],
            'qualification_percentage' => $rule->qualificationPercentage,
            'required_score' => $rule->requiredScore,
        ];
    }
}
