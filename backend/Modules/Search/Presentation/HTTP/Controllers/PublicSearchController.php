<?php

declare(strict_types=1);

namespace Modules\Search\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;
use Modules\Streaming\Infrastructure\Database\Models\StreamModel;

final class PublicSearchController extends Controller
{
    /**
     * GET /api/v1/search
     * Multi-entity search endpoint powered by Scout/Database search.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'contestants' => [],
                    'streams' => [],
                ],
            ]);
        }

        $contestants = ContestantModel::query()
            ->where('full_name', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'full_name', 'country_id']);

        $streams = StreamModel::query()
            ->where('title', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'title', 'status', 'hls_url']);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $q,
                'contestants' => $contestants,
                'streams' => $streams,
            ],
        ]);
    }
}
