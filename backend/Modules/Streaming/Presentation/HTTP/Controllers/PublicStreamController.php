<?php

declare(strict_types=1);

namespace Modules\Streaming\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Streaming\Infrastructure\Database\Models\StreamModel;
use Modules\Streaming\Presentation\HTTP\Resources\StreamResource;

/**
 * PublicStreamController
 *
 * Public playback access to active live streams for contestants & visitors.
 */
final class PublicStreamController extends Controller
{
    /**
     * GET /api/v1/streams/live
     * Returns active live streams (status = live).
     */
    public function live(Request $request): JsonResponse
    {
        $query = StreamModel::query()
            ->where('status', 'live')
            ->orderByDesc('actual_start');

        if ($seasonId = $request->query('season_id')) {
            $query->where('season_id', $seasonId);
        }

        $streams = $query->get();

        return response()->json([
            'success' => true,
            'data' => StreamResource::collection($streams),
        ]);
    }

    /**
     * GET /api/v1/streams/{id}
     * Shows public stream information and HLS playback URL.
     */
    public function show(string $id): JsonResponse
    {
        $stream = StreamModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new StreamResource($stream),
        ]);
    }
}
