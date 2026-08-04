<?php

declare(strict_types=1);

namespace Modules\Evaluations\Application\UseCases;

use Modules\Evaluations\Domain\Repositories\EvaluationRepositoryContract;
use Modules\Evaluations\Domain\Services\EvaluationStateMachine;
use Modules\Evaluations\Domain\Services\RankingService;

final readonly class SubmitEvaluationUseCase
{
    public function __construct(
        private EvaluationRepositoryContract $evaluationRepo,
        private RankingService $rankingService,
    ) {}

    public function execute(string $evaluationId, string $judgeId, array $criteriaScores, ?string $notes, EvaluationStateMachine $stateMachine, int $requiredJudgeCount = 5): array
    {
        $evaluation = $this->evaluationRepo->findOrFail($evaluationId);

        if ($evaluation->judgeId !== $judgeId) {
            throw new \DomainException('Judge is not assigned to this evaluation.');
        }

        if (in_array($evaluation->getStatus(), ['submitted', 'locked', 'approved'])) {
            throw new \DomainException('Evaluation already submitted and locked.');
        }

        $evaluation->submitScores($criteriaScores, $notes, $stateMachine);
        $this->evaluationRepo->save($evaluation);

        $submittedCount = $this->evaluationRepo->countSubmittedByApplication($evaluation->applicationId);
        $allCompleted = $submittedCount >= $requiredJudgeCount;

        return [
            'evaluation' => $evaluation,
            'all_completed' => $allCompleted,
            'submitted_count' => $submittedCount,
        ];
    }
}
