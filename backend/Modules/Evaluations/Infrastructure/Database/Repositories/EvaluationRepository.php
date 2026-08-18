<?php

declare(strict_types=1);

namespace Modules\Evaluations\Infrastructure\Database\Repositories;

use Modules\Evaluations\Domain\Entities\Evaluation;
use Modules\Evaluations\Domain\Repositories\EvaluationRepositoryContract;
use Modules\Evaluations\Domain\ValueObjects\EvaluationId;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationScoreModel;
use Symfony\Component\Uid\Uuid;

final class EvaluationRepository implements EvaluationRepositoryContract
{
    public function findOrFail(string $id): Evaluation
    {
        $model = EvaluationModel::query()->findOrFail($id);

        return $this->toDomain($model);
    }

    public function findByAppAndJudge(string $appId, string $judgeId): ?Evaluation
    {
        $model = EvaluationModel::query()
            ->where('application_id', $appId)
            ->where('judge_id', $judgeId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByJudge(string $judgeId, ?string $status = null): array
    {
        $query = EvaluationModel::query()->where('judge_id', $judgeId);
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function countSubmittedByApplication(string $applicationId): int
    {
        return EvaluationModel::query()
            ->where('application_id', $applicationId)
            ->where('status', 'submitted')
            ->count();
    }

    public function findAllByApplication(string $applicationId): array
    {
        return EvaluationModel::query()
            ->where('application_id', $applicationId)
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    public function save(Evaluation $evaluation): void
    {
        EvaluationModel::query()->updateOrCreate(
            ['id' => $evaluation->id->value],
            [
                'application_id' => $evaluation->applicationId,
                'judge_id' => $evaluation->judgeId,
                'total_score' => $evaluation->getTotalScore(),
                'notes' => $evaluation->getNotes(),
                'status' => $evaluation->getStatus(),
            ]
        );

        foreach ($evaluation->getCriteriaScores() as $criterionId => $score) {
            $scoreModel = EvaluationScoreModel::query()->firstOrNew([
                'evaluation_id' => $evaluation->id->value,
                'criterion_id' => $criterionId,
            ]);

            if (! $scoreModel->exists) {
                $scoreModel->id = (string) Uuid::v7();
            }

            $scoreModel->score = $score;
            $scoreModel->save();
        }
    }

    private function toDomain(EvaluationModel $model): Evaluation
    {
        $criteriaScores = EvaluationScoreModel::query()
            ->where('evaluation_id', $model->id)
            ->pluck('score', 'criterion_id')
            ->map(fn ($score): float => (float) $score)
            ->all();

        return new Evaluation(
            id: new EvaluationId($model->id),
            applicationId: $model->application_id,
            judgeId: $model->judge_id,
            status: $model->status,
            totalScore: (float) $model->total_score,
            notes: $model->notes,
            criteriaScores: $criteriaScores
        );
    }
}
