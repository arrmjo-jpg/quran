<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Domain\Exceptions\NotificationNotRetryableException;
use Modules\Notifications\Domain\Exceptions\TemplateNotRetryableException;
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
     *   ?status=queued|sent|failed          (ADR-020 D6: no `retrying`)
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
     * Sends a failed notification again -- ADR-020 D5.
     *
     * IT NOW ACTUALLY SENDS. Until this story the handler wrote a status and
     * returned "Notification queued for retry" with nothing queued: the line
     * after the write was a literal
     * `// TODO: dispatch RetryNotificationJob::dispatch($log->id)`.
     * NotificationDeliveryGoldenMasterTest recorded that, and now asserts the
     * job is pushed.
     *
     * EVERY DECISION MOVED OUT OF HERE. The status guard belongs to the
     * aggregate and the dispatch to the service, so this handler only turns
     * two domain refusals into two status codes. The old version reached
     * NotificationLogModel directly, past its own repository, which is the
     * only reason it was able to write a `retrying` status the domain does
     * not model (D6).
     */
    public function retry(string $id, NotificationsServiceContract $notifications): JsonResponse
    {
        // Kept so an unknown id is still a 404 rather than a 500 from the
        // repository, and so the refreshed row can be returned below.
        $log = NotificationLogModel::query()->findOrFail($id);

        try {
            $notifications->retry($id);
        } catch (NotificationNotRetryableException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FAILED', 'message' => $e->getMessage()],
            ], 409);
        } catch (TemplateNotRetryableException $e) {
            // 409 as well, and deliberately not 500: nothing is broken. The
            // platform is being asked for something it has never been taught
            // how to do, and saying so is the whole point of the exception.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_RETRYABLE', 'message' => $e->getMessage()],
            ], 409);
        } catch (DomainException $e) {
            // The rebuild itself refused. Core's factory re-issues the
            // invitation, and IssueInvitationUseCase declines an account that
            // has since been claimed -- an invitation to an activated account
            // is a password reset wearing an invitation's clothes. That is a
            // legitimate no, not a fault, so it is a 409 carrying the reason
            // rather than a 500 carrying a stack trace.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CANNOT_REBUILD', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification queued for retry.',
            'data' => new NotificationLogResource($log->fresh()),
        ]);
    }
}
