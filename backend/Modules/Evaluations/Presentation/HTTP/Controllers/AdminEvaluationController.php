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
use Symfony\Component\Uid\Uuid;

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

        // 2. Aggregate average scores per application from the real
        // per-criterion breakdown each judge submitted (evaluation_scores),
        // grouped by evaluation_criteria.code ('tajweed', 'memorization',
        // 'voice', 'performance' — only the first three feed tie-breaking).
        $applicationScores = [];
        foreach ($applicationIds as $appId) {
            $evalIds = EvaluationModel::query()
                ->where('application_id', $appId)
                ->where('status', 'submitted')
                ->pluck('id');

            if ($evalIds->isEmpty()) {
                continue;
            }

            $avgTotal = (float) EvaluationModel::query()->whereIn('id', $evalIds)->avg('total_score');

            $categoryAverages = DB::table('evaluation_scores')
                ->join('evaluation_criteria', 'evaluation_scores.criterion_id', '=', 'evaluation_criteria.id')
                ->whereIn('evaluation_scores.evaluation_id', $evalIds)
                ->selectRaw('evaluation_criteria.code as code, AVG(evaluation_scores.score) as avg_score')
                ->groupBy('evaluation_criteria.code')
                ->pluck('avg_score', 'code');

            $applicationScores[] = [
                'application_id' => $appId,
                'total_score' => round($avgTotal, 2),
                'tajweed_score' => round((float) ($categoryAverages['tajweed'] ?? 0.0), 2),
                'memorization_score' => round((float) ($categoryAverages['memorization'] ?? 0.0), 2),
                'voice_score' => round((float) ($categoryAverages['voice'] ?? 0.0), 2),
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

        // 4. Persist per-application results into `results` table.
        // Not using updateOrInsert()/updateOrCreate() with a fresh id in the
        // values array: on an existing row that silently overwrites the
        // primary key (and resets created_at) on every recalculation.
        $scoresByApplication = collect($applicationScores)->keyBy('application_id');

        DB::transaction(function () use ($rankings, $scoresByApplication, $stageId): void {
            foreach ($rankings as $item) {
                $breakdown = $scoresByApplication[$item['application_id']];
                $attributes = [
                    'final_score' => $item['final_score'],
                    'tajweed_score' => $breakdown['tajweed_score'],
                    'memorization_score' => $breakdown['memorization_score'],
                    'voice_score' => $breakdown['voice_score'],
                    'rank' => $item['rank'],
                    'status' => $item['qualification_status'],
                    'manual_tie_break_flag' => $item['manual_tie_break_flag'] ? 1 : 0,
                    'updated_at' => now(),
                ];

                $existingId = DB::table('results')->where('application_id', $item['application_id'])->value('id');

                if ($existingId) {
                    DB::table('results')->where('id', $existingId)->update($attributes);
                } else {
                    DB::table('results')->insert($attributes + [
                        'id' => (string) Uuid::v7(),
                        'application_id' => $item['application_id'],
                        'created_at' => now(),
                    ]);
                }
            }

            // Upsert stage_results header as draft, preserving the id/created_at
            // of an existing row instead of regenerating them on every run.
            $stageResult = StageResultModel::query()->firstOrNew(['stage_id' => $stageId]);
            if (! $stageResult->exists) {
                $stageResult->id = (string) Uuid::v7();
            }
            $stageResult->status = 'draft';
            $stageResult->save();
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
