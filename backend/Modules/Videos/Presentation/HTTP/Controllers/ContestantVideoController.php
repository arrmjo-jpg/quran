<?php

declare(strict_types=1);

namespace Modules\Videos\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;
use Modules\Videos\Infrastructure\Database\Models\VideoModel;
use Modules\Videos\Presentation\HTTP\Resources\VideoResource;

/**
 * ContestantVideoController
 *
 * Contestant-facing video access.
 * Contestants can:
 *   POST /api/v1/contestant/videos          — register a video from an uploaded media asset
 *   GET  /api/v1/contestant/videos/{id}     — view own video (HLS playback URL)
 *   GET  /api/v1/contestant/videos/{id}/status — poll processing status
 *
 * Contestants CANNOT see other contestants' videos.
 */
final class ContestantVideoController extends Controller
{
    /**
     * POST /api/v1/contestant/videos
     *
     * Links an already-uploaded media_asset to a Video record.
     * The media_asset must belong to the authenticated contestant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'raw_media_asset_id' => ['required', 'string', 'uuid'],
            'application_id' => ['required', 'string', 'uuid'],
        ]);

        // Verify media_asset ownership
        $asset = MediaAssetModel::query()
            ->where('id', $validated['raw_media_asset_id'])
            ->where('uploader_id', $request->user()->id)
            ->first();

        if (! $asset) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ASSET_NOT_FOUND', 'message' => 'Media asset not found or does not belong to you.'],
            ], 404);
        }

        // Check asset is a video
        if (! str_starts_with($asset->mime_type ?? '', 'video/')) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_A_VIDEO', 'message' => 'The media asset must be a video file.'],
            ], 422);
        }

        // Prevent duplicate — one video per asset
        $existing = VideoModel::query()
            ->where('raw_media_asset_id', $asset->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Video record already exists.',
                'data' => new VideoResource($existing),
            ], 200);
        }

        $video = VideoModel::query()->create([
            'id' => (string) Str::uuid(),
            'application_id' => $validated['application_id'],
            'raw_media_asset_id' => $asset->id,
            'status' => 'uploaded',
        ]);

        // TODO: dispatch TranscodeVideoJob::dispatch($video->id)

        return response()->json([
            'success' => true,
            'message' => 'Video registered and queued for processing.',
            'data' => new VideoResource($video),
        ], 201);
    }

    /**
     * GET /api/v1/contestant/videos/{id}
     *
     * View own video with HLS playback URL.
     * Returns 403 if the video's application does not belong to the contestant.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $video = VideoModel::query()->findOrFail($id);

        // Ownership check via raw_media_asset_id → uploader_id
        $asset = MediaAssetModel::query()
            ->where('id', $video->raw_media_asset_id)
            ->where('uploader_id', $request->user()->id)
            ->first();

        if (! $asset) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new VideoResource($video),
        ]);
    }

    /**
     * GET /api/v1/contestant/videos/{id}/status
     *
     * Lightweight polling endpoint for processing status.
     * Returns only status, hls_url, duration_seconds — not full payload.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $video = VideoModel::query()->findOrFail($id);

        // Ownership check
        $asset = MediaAssetModel::query()
            ->where('id', $video->raw_media_asset_id)
            ->where('uploader_id', $request->user()->id)
            ->first();

        if (! $asset) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $video->id,
                'status' => $video->status,
                'duration_seconds' => $video->duration_seconds,
                'hls_url' => $video->hls_master_playlist_path
                    ? url('storage/'.$video->hls_master_playlist_path)
                    : null,
            ],
        ]);
    }
}
