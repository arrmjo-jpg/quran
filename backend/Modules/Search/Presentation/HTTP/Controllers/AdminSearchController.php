<?php

declare(strict_types=1);

namespace Modules\Search\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Search\Infrastructure\Database\Models\IndexingLogModel;

final class AdminSearchController extends Controller
{
    /**
     * POST /api/v1/admin/search/reindex
     */
    public function reindex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_name' => ['required', 'string', 'in:all,users,contestants,videos,announcements'],
        ]);

        $log = IndexingLogModel::query()->create([
            'id' => (string) Str::uuid(),
            'index_name' => $validated['index_name'],
            'action' => 'flush_and_import',
            'status' => 'success',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Reindex requested for target [{$validated['index_name']}].",
            'data' => $log,
        ]);
    }

    /**
     * GET /api/v1/admin/search/indexing-logs
     */
    public function indexingLogs(Request $request): JsonResponse
    {
        $logs = IndexingLogModel::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
