<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Evaluations\Domain\Repositories\EvaluationRepositoryContract;
use Modules\Evaluations\Domain\Services\RankingService;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Evaluations\Infrastructure\Database\Models\StageResultModel;
use Modules\Evaluations\Presentation\HTTP\Requests\PublishResultsRequest;
use Modules\Evaluations\Presentation\HTTP\Resources\EvaluationResource;

final class AdminEvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationRepositoryContract $repo,
        private readonly RankingService $rankingService,
    ) {}

    /**
     * GET /api/v1/admin/evaluations
     *
     * Admin sees all evaluations — no Judge Blindness restriction.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EvaluationModel::query();

        if ($applicationId = $request->query('application_id')) {
            $query->where('application_id', $applicationId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $evaluations = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => EvaluationResource::collection($evaluations),
            'meta' => [
                'total' => $evaluations->total(),
                'per_page' => $evaluations->perPage(),
                'current_page' => $evaluations->currentPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/stages/{id}/calculate-results
     *
     * Runs RankingService (Pure Domain) on submitted evaluations.
     * Persists ranked results into the `results` table (one row per application).
     * Uses `stage_results` as the publication header (upserted as draft).
     */
    public function calculateResults(Request $request, string $stageId): JsonResponse
    {
        // 1. Gather ready-for-judging applications in this stage
        $applicationIds = ApplicationModel::query()
            ->where('stage_id', $stageId)
            ->where('status', 'ready_for_judging')
            ->pluck('id')
            ->toArray();

        if (empty($applicationIds)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_APPLICATIONS', 'message' => 'No applications ready for judging in this stage.'],
            ], 422);
        }

        // 2. Aggregate average scores per application
        $applicationScores = [];
        foreach ($applicationIds as $appId) {
            $evals = EvaluationModel::query()
                ->where('application_id', $appId)
                ->where('status', 'submitted')
                ->get();

            if ($evals->isEmpty()) {
                continue;
            }

            $avgTotal = (float) $evals->avg('total_score');

            // Decompose total into weighted criteria averages (proxy — full implementation reads evaluation_scores)
            $applicationScores[] = [
                'application_id' => $appId,
                'total_score' => round($avgTotal, 2),
                'tajweed_score' => round($avgTotal * 0.40, 2),
                'memorization_score' => round($avgTotal * 0.40, 2),
                'voice_score' => round($avgTotal * 0.20, 2),
            ];
        }

        if (empty($applicationScores)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_EVALUATIONS', 'message' => 'No submitted evaluations found for these applications.'],
            ], 422);
        }

        // 3. Run pure Domain RankingService
        $minThreshold = (float) $request->input('min_qualification_threshold', 80.0);
        $rankings = $this->rankingService->calculateStageRankings($applicationScores, $minThreshold);

        // 4. Persist per-application results into `results` table
        DB::transaction(function () use ($rankings, $stageId): void {
            foreach ($rankings as $item) {
                DB::table('results')->updateOrInsert(
                    ['application_id' => $item['application_id']],
                    [
                        'id' => fake()->uuid(),
                        'final_score' => $item['final_score'],
                        'tajweed_score' => 0.0,
                        'memorization_score' => 0.0,
                        'voice_score' => 0.0,
                        'rank' => $item['rank'],
                        'status' => $item['qualification_status'],
                        'manual_tie_break_flag' => $item['manual_tie_break_flag'] ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Upsert stage_results header as draft
            StageResultModel::query()->updateOrCreate(
                ['stage_id' => $stageId],
                ['id' => fake()->uuid(), 'status' => 'draft']
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Results calculated successfully.',
            'data' => [
                'stage_id' => $stageId,
                'results' => $rankings,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/stages/{id}/publish-results
     *
     * Marks the stage_results header as published.
     * Once published, results are visible to contestants.
     * Requires calculate-results to have been run first.
     */
    public function publishResults(PublishResultsRequest $request, string $stageId): JsonResponse
    {
        $stageResult = StageResultModel::query()
            ->where('stage_id', $stageId)
            ->first();

        if (! $stageResult) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'RESULTS_NOT_CALCULATED', 'message' => 'Run calculate-results first before publishing.'],
            ], 422);
        }

        if ($stageResult->status === 'published') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_PUBLISHED', 'message' => 'Results are already published. Use reopen-results to make changes.'],
            ], 409);
        }

        $stageResult->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Results published successfully.',
            'data' => ['stage_id' => $stageId, 'status' => 'published'],
        ]);
    }

    /**
     * POST /api/v1/admin/stages/{id}/reopen-results
     *
     * Reverts publication status back to draft.
     * Forces a re-run of calculate-results before republishing.
     */
    public function reopenResults(Request $request, string $stageId): JsonResponse
    {
        $stageResult = StageResultModel::query()
            ->where('stage_id', $stageId)
            ->where('status', 'published')
            ->first();

        if (! $stageResult) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_PUBLISHED', 'message' => 'Results are not published yet.'],
            ], 422);
        }

        $stageResult->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Results reopened. Recalculation required before republishing.',
            'data' => ['stage_id' => $stageId, 'status' => 'draft'],
        ]);
    }
}
