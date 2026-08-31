<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Modules\Notifications\Presentation\HTTP\Requests\UpdateNotificationPreferencesRequest;

/**
 * An account's own notification preferences — ADR-020 D4, D9.
 *
 * SELF-SERVICE, WITH NO PERMISSION, and that is a decision rather than an
 * omission. A permission answers "which ROLE may do this", and the answer here
 * is "the account whose preferences these are" — which no role can express.
 * ADR-018 D4 settled the same question the same way for MFA, and both route
 * names are listed in SELF_SERVICE_ROUTES so the architecture guard checks the
 * exemption rather than being blind to it.
 *
 * NOTHING HERE READS OR WRITES ANOTHER ACCOUNT. The user id comes from the
 * authenticated request and never from the payload, so there is no object to
 * authorize and no way to address someone else's settings.
 *
 * Endpoints:
 *   GET /api/v1/admin/me/notification-preferences
 *   PUT /api/v1/admin/me/notification-preferences
 */
final class NotificationPreferenceController extends Controller
{
    /**
     * Every notification this account may decline, and whether it currently
     * receives it.
     *
     * TODAY THIS IS AN EMPTY OBJECT. `invitation.created` is the only type the
     * platform sends and D9 makes it mandatory, so there is nothing to list —
     * a measurement, not a fault. The response shape is the same either way,
     * so a caller written now keeps working when the first optional
     * notification appears.
     */
    public function index(NotificationsServiceContract $notifications): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'preferences' => (object) $notifications->preferencesFor((string) request()->user()->id),
            ],
        ]);
    }

    /**
     * Replace this account's decisions.
     *
     * Two refusals, both 409 and both carrying their reason: a type nobody has
     * declared, and a type that may never be declined (D9). Neither is a 500 —
     * the request is well-formed and the answer is no.
     */
    public function update(
        UpdateNotificationPreferencesRequest $request,
        NotificationsServiceContract $notifications
    ): JsonResponse {
        // request()->user(), not $request->user(): a FormRequest is built with
        // Request::createFrom(), which copies the attribute bag by value. The
        // same trap cost a story in ADR-018.
        $userId = (string) request()->user()->id;

        /** @var array<string, bool> $wanted */
        $wanted = $request->validated('preferences');

        try {
            foreach ($wanted as $type => $enabled) {
                $notifications->setPreference($userId, $type, $enabled);
            }
        } catch (DomainException $e) {
            // A refusal part-way through leaves the earlier types applied, and
            // that is acceptable here rather than an oversight: preferences are
            // independent booleans with no invariant between them, so rolling
            // back would buy a consistency nobody can observe. The message
            // names the type that was refused, and a GET returns the whole
            // current state, so nothing has to be guessed.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PREFERENCE_REFUSED', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated.',
            'data' => [
                'preferences' => (object) $notifications->preferencesFor($userId),
            ],
        ]);
    }
}
