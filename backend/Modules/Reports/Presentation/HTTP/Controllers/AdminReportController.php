<?php

declare(strict_types=1);

namespace Modules\Reports\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Reports\Infrastructure\Database\Models\ExportModel;
use Modules\Reports\Presentation\HTTP\Resources\ExportResource;

final class AdminReportController extends Controller
{
    /**
     * GET /api/v1/admin/reports/summary
     * Aggregate executive dashboard metrics.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_contestants' => ContestantModel::query()->count(),
                'total_applications' => ApplicationModel::query()->count(),
                'applications_by_status' => [
                    'submitted' => ApplicationModel::query()->where('status', 'submitted')->count(),
                    'ready_for_judging' => ApplicationModel::query()->where('status', 'ready_for_judging')->count(),
                    'under_evaluation' => ApplicationModel::query()->where('status', 'under_evaluation')->count(),
                    'completed' => ApplicationModel::query()->where('status', 'completed')->count(),
                ],
                'total_evaluations' => EvaluationModel::query()->count(),
                'completed_evaluations' => EvaluationModel::query()->where('status', 'submitted')->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/reports/exports
     */
    public function indexExports(Request $request): JsonResponse
    {
        $exports = ExportModel::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ExportResource::collection($exports),
            'meta' => [
                'total' => $exports->total(),
                'per_page' => $exports->perPage(),
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/reports/exports
     */
    public function createExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:contestants_csv,evaluations_pdf,results_excel'],
        ]);

        $export = ExportModel::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Export job created.',
            'data' => new ExportResource($export),
        ], 201);
    }
}
