<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluations\Application\UseCases\SubmitEvaluationUseCase;
use Modules\Evaluations\Domain\Repositories\EvaluationRepositoryContract;
use Modules\Evaluations\Domain\Services\EvaluationStateMachine;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Evaluations\Presentation\HTTP\Requests\SaveDraftEvaluationRequest;
use Modules\Evaluations\Presentation\HTTP\Requests\StartEvaluationRequest;
use Modules\Evaluations\Presentation\HTTP\Requests\SubmitEvaluationRequest;
use Modules\Evaluations\Presentation\HTTP\Resources\EvaluationResource;
use Modules\Judges\Domain\Repositories\JudgeRepositoryContract;

final class JudgeEvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationRepositoryContract $repo,
        private readonly JudgeRepositoryContract $judgeRepo,
        private readonly EvaluationStateMachine $stateMachine,
    ) {}

    /**
     * GET /api/v1/judge/evaluations
     * Returns only THIS judge's own assigned evaluations — BLIND (no other judge data)
     */
    public function index(Request $request): JsonResponse
    {
        $judge = $this->judgeRepo->findByUserId((string) $request->user()->id);
        if (! $judge) {
            return response()->json(['success' => false, 'error' => ['code' => 'JUDGE_PROFILE_REQUIRED', 'message' => 'Create judge profile first.']], 422);
        }

        $evaluations = $this->repo->findByJudge((string) $judge->id);

        return response()->json([
            'success' => true,
            'data' => EvaluationResource::collection(collect($evaluations)),
        ]);
    }

    /**
     * GET /api/v1/judge/evaluations/{id}
     * Judge Blindness Guard: only returns own evaluation
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $judge = $this->judgeRepo->findByUserId((string) $request->user()->id);
        if (! $judge) {
            return response()->json(['success' => false, 'error' => ['code' => 'JUDGE_PROFILE_REQUIRED', 'message' => 'Create judge profile first.']], 422);
        }

        $evaluation = $this->repo->findOrFail($id);

        // JUDGE BLINDNESS: can only see own evaluations
        if ($evaluation->judgeId !== (string) $judge->id) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        return response()->json(['success' => true, 'data' => new EvaluationResource($evaluation)]);
    }

    /**
     * POST /api/v1/judge/evaluations/{id}/start
     * Transition evaluation from pending→in_progress
     */
    public function start(StartEvaluationRequest $request, string $id): JsonResponse
    {
        $judge = $this->judgeRepo->findByUserId((string) $request->user()->id);
        if (! $judge) {
            return response()->json(['success' => false, 'error' => ['code' => 'JUDGE_PROFILE_REQUIRED', 'message' => 'Create judge profile first.']], 422);
        }

        $evaluation = $this->repo->findOrFail($id);

        if ($evaluation->judgeId !== (string) $judge->id) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        try {
            $evaluation->beginReview($this->stateMachine);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATE', 'message' => 'Evaluation already started.']], 409);
        }

        $this->repo->save($evaluation);

        return response()->json(['success' => true, 'message' => 'Evaluation started.', 'data' => ['id' => $id, 'status' => $evaluation->getStatus()]]);
    }

    /**
     * POST /api/v1/judge/evaluations/{id}/submit
     * Submit final scores — LOCKS the evaluation
     */
    public function submit(SubmitEvaluationRequest $request, string $id, SubmitEvaluationUseCase $useCase): JsonResponse
    {
        $judge = $this->judgeRepo->findByUserId((string) $request->user()->id);
        if (! $judge) {
            return response()->json(['success' => false, 'error' => ['code' => 'JUDGE_PROFILE_REQUIRED', 'message' => 'Create judge profile first.']], 422);
        }

        // Build criteria_scores as criterion_id => score map
        $criteriaScores = [];
        foreach ($request->validated('criteria_scores') as $entry) {
            $criteriaScores[$entry['criterion_id']] = (float) $entry['score'];
        }

        try {
            $result = $useCase->execute(
                evaluationId: $id,
                judgeId: (string) $judge->id,
                criteriaScores: $criteriaScores,
                notes: $request->validated('notes'),
                stateMachine: $this->stateMachine,
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage()]], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()]], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evaluation submitted successfully.',
            'data' => new EvaluationResource($result['evaluation']),
            'meta' => [
                'all_judges_completed' => $result['all_completed'],
            ],
        ]);
    }

    /**
     * POST /api/v1/judge/evaluations/{id}/save-draft
     * Save progress without submitting
     */
    public function saveDraft(SaveDraftEvaluationRequest $request, string $id): JsonResponse
    {
        $judge = $this->judgeRepo->findByUserId((string) $request->user()->id);
        if (! $judge) {
            return response()->json(['success' => false, 'error' => ['code' => 'JUDGE_PROFILE_REQUIRED', 'message' => 'Create judge profile first.']], 422);
        }

        $evaluation = $this->repo->findOrFail($id);

        if ($evaluation->judgeId !== (string) $judge->id) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        if (in_array($evaluation->getStatus(), ['submitted', 'locked', 'approved'])) {
            return response()->json(['success' => false, 'error' => ['code' => 'ALREADY_LOCKED', 'message' => 'Cannot edit submitted evaluation.']], 409);
        }

        // Save draft scores without state transition
        $scores = $request->validated('criteria_scores');
        $totalScore = $scores ? array_sum(array_column($scores, 'score')) : $evaluation->getTotalScore();

        EvaluationModel::query()
            ->where('id', $id)
            ->update([
                'notes' => $request->validated('notes') ?? $evaluation->getNotes(),
                'total_score' => $totalScore,
            ]);

        return response()->json(['success' => true, 'message' => 'Draft saved.', 'data' => ['id' => $id]]);
    }
}
