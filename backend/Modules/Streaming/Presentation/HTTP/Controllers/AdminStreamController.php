<?php

declare(strict_types=1);

namespace Modules\Streaming\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Streaming\Infrastructure\Database\Models\StreamModel;
use Modules\Streaming\Presentation\HTTP\Resources\StreamResource;

/**
 * AdminStreamController
 *
 * Administrative management of live streams for competition stages.
 */
final class AdminStreamController extends Controller
{
    /**
     * GET /api/v1/admin/streams
     */
    public function index(Request $request): JsonResponse
    {
        $query = StreamModel::query()->orderByDesc('created_at');

        if ($seasonId = $request->query('season_id')) {
            $query->where('season_id', $seasonId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $streams = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => StreamResource::collection($streams),
            'meta' => [
                'total' => $streams->total(),
                'per_page' => $streams->perPage(),
                'current_page' => $streams->currentPage(),
                'last_page' => $streams->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/streams
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'season_id' => ['required', 'string', 'uuid'],
            'stage_id' => ['nullable', 'string', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_start' => ['nullable', 'date'],
        ]);

        $streamKey = 'live_'.Str::random(24);
        $rtmpBase = config('services.rtmp.base_url', 'rtmp://live.quran.test/live');

        $stream = StreamModel::query()->create([
            'id' => (string) Str::uuid(),
            'season_id' => $validated['season_id'],
            'stage_id' => $validated['stage_id'] ?? null,
            'title' => $validated['title'],
            'stream_key' => $streamKey,
            'rtmp_url' => "{$rtmpBase}/{$streamKey}",
            'hls_url' => "http://live.quran.test/hls/{$streamKey}.m3u8",
            'status' => 'idle',
            'scheduled_start' => $validated['scheduled_start'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stream created successfully.',
            'data' => new StreamResource($stream),
        ], 201);
    }

    /**
     * GET /api/v1/admin/streams/{id}
     */
    public function show(string $id): JsonResponse
    {
        $stream = StreamModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new StreamResource($stream),
        ]);
    }

    /**
     * POST /api/v1/admin/streams/{id}/start
     */
    public function start(string $id): JsonResponse
    {
        $stream = StreamModel::query()->findOrFail($id);

        if ($stream->status === 'live') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_LIVE', 'message' => 'Stream is already live.'],
            ], 409);
        }

        $stream->update([
            'status' => 'live',
            'actual_start' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stream status updated to live.',
            'data' => new StreamResource($stream->fresh()),
        ]);
    }

    /**
     * POST /api/v1/admin/streams/{id}/stop
     */
    public function stop(string $id): JsonResponse
    {
        $stream = StreamModel::query()->findOrFail($id);

        if ($stream->status === 'ended') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_ENDED', 'message' => 'Stream has already ended.'],
            ], 409);
        }

        $stream->update([
            'status' => 'ended',
            'actual_end' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stream ended.',
            'data' => new StreamResource($stream->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/admin/streams/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $stream = StreamModel::query()->findOrFail($id);
        $stream->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stream record deleted.',
        ]);
    }
}
