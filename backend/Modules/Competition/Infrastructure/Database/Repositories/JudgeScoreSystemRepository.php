<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Repositories;

use Modules\Competition\Domain\Repositories\JudgeScoreSystemRepositoryContract;
use Modules\Competition\Domain\ValueObjects\ResolvedJudgeScoreSystem;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;

final class JudgeScoreSystemRepository implements JudgeScoreSystemRepositoryContract
{
    public function findOrFail(string $id): ResolvedJudgeScoreSystem
    {
        $model = JudgeScoreSystemModel::query()->with('translations')->findOrFail($id);

        return new ResolvedJudgeScoreSystem(
            id: $model->id,
            code: $model->code,
            name: $model->translations->pluck('name', 'locale')->toArray(),
            maxScore: (float) $model->max_score,
        );
    }
}
