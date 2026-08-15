<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;
use Modules\Competition\Domain\ValueObjects\StageRuleAssignment;
use Modules\Competition\Infrastructure\Database\Models\SeasonStageRuleModel;

final class SeasonStageRuleRepository implements SeasonStageRuleRepositoryContract
{
    public function findResolvedRules(string $seasonId): array
    {
        $rules = SeasonStageRuleModel::query()
            ->where('season_id', $seasonId)
            ->with(['stage.translations', 'judgeScoreSystem.translations'])
            ->get();

        return $rules
            ->sortBy(fn (SeasonStageRuleModel $rule): int => (int) $rule->stage->stage_number)
            ->map(function (SeasonStageRuleModel $rule): ResolvedStageRule {
                $stage = $rule->stage;
                $scoreSystem = $rule->judgeScoreSystem;

                return new ResolvedStageRule(
                    stageId: $stage->id,
                    stageNumber: (int) $stage->stage_number,
                    type: $stage->type,
                    name: $stage->translations->pluck('name', 'locale')->toArray(),
                    judgeScoreSystem: new ResolvedLookupOption(
                        id: $scoreSystem->id,
                        code: $scoreSystem->code,
                        name: $scoreSystem->translations->pluck('name', 'locale')->toArray(),
                    ),
                    judgeScoreSystemMaxScore: (float) $scoreSystem->max_score,
                    qualificationPercentage: $rule->qualification_percentage !== null
                        ? (float) $rule->qualification_percentage
                        : null,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, StageRuleAssignment>  $assignments
     */
    public function replaceAll(string $seasonId, array $assignments): void
    {
        SeasonStageRuleModel::query()->where('season_id', $seasonId)->delete();

        foreach ($assignments as $assignment) {
            SeasonStageRuleModel::query()->create([
                'id' => (string) Str::uuid(),
                'season_id' => $seasonId,
                'stage_id' => $assignment->stageId,
                'judge_score_system_id' => $assignment->judgeScoreSystemId,
                'qualification_percentage' => $assignment->qualificationPercentage,
            ]);
        }
    }
}
