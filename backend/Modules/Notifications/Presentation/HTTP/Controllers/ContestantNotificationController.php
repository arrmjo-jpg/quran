<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Modules\Notifications\Presentation\HTTP\Resources\NotificationLogResource;

/**
 * ContestantNotificationController
 *
 * Contestant-facing notification history.
 * Contestants can ONLY view their own logs — no payload exposure, no admin fields.
 *
 * Endpoints:
 *   GET /api/v1/contestant/notifications       — own notification history
 *   GET /api/v1/contestant/notifications/{id}  — view own single notification
 */
final class ContestantNotificationController extends Controller
{
    /**
     * GET /api/v1/contestant/notifications
     *
     * Returns the authenticated user's own notification log.
     * Filters: ?channel=email|sms|push  ?status=sent|failed
     */
    public function index(Request $request): JsonResponse
    {
        $query = NotificationLogModel::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) ($request->query('per_page', 20)), 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => NotificationLogResource::collection($logs),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/contestant/notifications/{id}
     *
     * View a single notification entry.
     * Returns 403 if the log belongs to another user.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $log = NotificationLogModel::query()->findOrFail($id);

        if ($log->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new NotificationLogResource($log),
        ]);
    }
}
