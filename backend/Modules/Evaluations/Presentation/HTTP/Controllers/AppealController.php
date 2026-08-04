<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Evaluations\Infrastructure\Database\Models\AppealModel;
use Modules\Evaluations\Presentation\HTTP\Requests\ResolveAppealRequest;
use Modules\Evaluations\Presentation\HTTP\Requests\SubmitAppealRequest;
use Modules\Evaluations\Presentation\HTTP\Resources\AppealResource;

final class AppealController extends Controller
{
    /**
     * POST /api/v1/contestant/appeals
     */
    public function store(SubmitAppealRequest $request): JsonResponse
    {
        $contestant = app(ContestantRepositoryContract::class)->findByUserId((string) $request->user()->id);
        if (! $contestant) {
            return response()->json(['success' => false, 'error' => ['code' => 'PROFILE_REQUIRED', 'message' => 'Contestant profile required.']], 422);
        }

        $appeal = AppealModel::query()->create([
            'id' => fake()->uuid(),
            'application_id' => $request->validated('application_id'),
            'contestant_id' => (string) $contestant->id->value,
            'reason' => $request->validated('reason'),
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Appeal submitted successfully.', 'data' => new AppealResource($appeal)], 201);
    }

    /**
     * GET /api/v1/admin/appeals
     */
    public function index(Request $request): JsonResponse
    {
        $query = AppealModel::query();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $appeals = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => AppealResource::collection($appeals),
            'meta' => ['total' => $appeals->total()],
        ]);
    }

    /**
     * POST /api/v1/admin/appeals/{id}/accept
     */
    public function accept(ResolveAppealRequest $request, string $id): JsonResponse
    {
        $appeal = AppealModel::query()->findOrFail($id);

        if ($appeal->status !== 'pending') {
            return response()->json(['success' => false, 'error' => ['code' => 'ALREADY_RESOLVED', 'message' => 'Appeal already resolved.']], 409);
        }

        $appeal->update([
            'status' => 'accepted',
            'admin_response' => $request->validated('admin_response'),
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Appeal accepted.', 'data' => new AppealResource($appeal->fresh())]);
    }

    /**
     * POST /api/v1/admin/appeals/{id}/reject
     */
    public function reject(ResolveAppealRequest $request, string $id): JsonResponse
    {
        $appeal = AppealModel::query()->findOrFail($id);

        if ($appeal->status !== 'pending') {
            return response()->json(['success' => false, 'error' => ['code' => 'ALREADY_RESOLVED', 'message' => 'Appeal already resolved.']], 409);
        }

        $appeal->update([
            'status' => 'rejected',
            'admin_response' => $request->validated('admin_response'),
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Appeal rejected.', 'data' => new AppealResource($appeal->fresh())]);
    }
}
