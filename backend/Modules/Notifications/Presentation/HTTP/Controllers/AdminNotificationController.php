<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;
use Modules\Notifications\Presentation\HTTP\Resources\NotificationLogResource;

/**
 * AdminNotificationController
 *
 * Admin oversight of all platform notification logs.
 *
 * Endpoints:
 *   GET  /api/v1/admin/notifications         — list all logs (filter by channel/status/user)
 *   GET  /api/v1/admin/notifications/{id}    — show single log with full payload
 *   POST /api/v1/admin/notifications/{id}/retry — retry a failed notification
 */
final class AdminNotificationController extends Controller
{
    /**
     * GET /api/v1/admin/notifications
     *
     * Filters:
     *   ?channel=email|sms|push
     *   ?status=queued|sent|failed|retrying
     *   ?user_id=uuid
     *   ?template_key=application.approved
     *   ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = NotificationLogModel::query()->orderByDesc('created_at');

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($templateKey = $request->query('template_key')) {
            $query->where('template_key', $templateKey);
        }

        $perPage = min((int) ($request->query('per_page', 20)), 100);
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
     * GET /api/v1/admin/notifications/{id}
     *
     * Show full log including payload (admin only — may contain PII).
     */
    public function show(string $id): JsonResponse
    {
        $log = NotificationLogModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                (new NotificationLogResource($log))->toArray(request()),
                ['payload' => $log->payload] // Admin sees full payload
            ),
        ]);
    }

    /**
     * POST /api/v1/admin/notifications/{id}/retry
     *
     * Manually retries a failed notification.
     * Only allowed on status=failed; returns 409 otherwise.
     */
    public function retry(string $id): JsonResponse
    {
        $log = NotificationLogModel::query()->findOrFail($id);

        if ($log->status !== 'failed') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FAILED',
                    'message' => 'Only failed notifications can be retried. Current status: '.$log->status,
                ],
            ], 409);
        }

        $log->update([
            'status' => 'retrying',
            'error' => null,
        ]);

        // TODO: dispatch RetryNotificationJob::dispatch($log->id)

        return response()->json([
            'success' => true,
            'message' => 'Notification queued for retry.',
            'data' => new NotificationLogResource($log->fresh()),
        ]);
    }
}
