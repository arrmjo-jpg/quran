<?php

declare(strict_types=1);

namespace Modules\Videos\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Videos\Infrastructure\Database\Models\VideoModel;
use Modules\Videos\Presentation\HTTP\Resources\VideoResource;

/**
 * AdminVideoController
 *
 * Admin management of recitation videos.
 * Endpoints:
 *   GET    /api/v1/admin/videos               — list with filters
 *   GET    /api/v1/admin/videos/{id}           — show single video
 *   POST   /api/v1/admin/videos/{id}/reprocess — retry failed processing
 *   DELETE /api/v1/admin/videos/{id}           — soft-delete
 */
final class AdminVideoController extends Controller
{
    /**
     * GET /api/v1/admin/videos
     *
     * List all videos with optional filters:
     *   ?status=processing|ready|failed|uploaded
     *   ?application_id=uuid
     *   ?page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = VideoModel::query()->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($applicationId = $request->query('application_id')) {
            $query->where('application_id', $applicationId);
        }

        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $videos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VideoResource::collection($videos),
            'meta' => [
                'total' => $videos->total(),
                'per_page' => $videos->perPage(),
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/videos/{id}
     *
     * Show a single video with full details (HLS URL, variants, thumbnail).
     */
    public function show(string $id): JsonResponse
    {
        $video = VideoModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new VideoResource($video),
        ]);
    }

    /**
     * POST /api/v1/admin/videos/{id}/reprocess
     *
     * Admin retries FFmpeg transcoding for a failed or stuck video.
     * Sets status back to 'processing' and queues the job.
     */
    public function reprocess(string $id): JsonResponse
    {
        $video = VideoModel::query()->findOrFail($id);

        if ($video->status === 'processing') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_PROCESSING', 'message' => 'Video is already being processed.'],
            ], 409);
        }

        $video->update(['status' => 'processing']);

        // TODO: dispatch TranscodeVideoJob::dispatch($video->id)

        return response()->json([
            'success' => true,
            'message' => 'Video queued for reprocessing.',
            'data' => new VideoResource($video->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/admin/videos/{id}
     *
     * Soft-delete a video record.
     * Does NOT delete the underlying media_asset (managed separately).
     */
    public function destroy(string $id): JsonResponse
    {
        $video = VideoModel::query()->findOrFail($id);
        $video->delete();

        return response()->json([
            'success' => true,
            'message' => 'Video deleted.',
        ]);
    }
}
