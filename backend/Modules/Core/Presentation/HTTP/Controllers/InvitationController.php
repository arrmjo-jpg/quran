<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Application\UseCases\AcceptInvitationUseCase;
use Modules\Core\Domain\Exceptions\InvalidInvitationException;
use Modules\Core\Presentation\HTTP\Requests\AcceptInvitationRequest;

/**
 * The public half of the invitation flow — ADR-016 D14, Story 3.
 *
 * Unauthenticated by necessity: the person here has no account they can sign
 * into yet, which is the whole point. The token is the only credential.
 *
 * There is no endpoint that reads an invitation. A GET that answered "this
 * token is valid, and it belongs to alice@example.com" would be a probe with
 * a reward attached, and it would leak an address to anyone holding a guess.
 * The form asks for a password without naming the account; the invitee knows
 * which mailbox the link arrived in.
 */
final class InvitationController extends Controller
{
    public function accept(AcceptInvitationRequest $request, AcceptInvitationUseCase $accept): JsonResponse
    {
        try {
            $accept->execute(
                $request->validated('token'),
                $request->validated('password'),
            );
        } catch (InvalidInvitationException $e) {
            // 422 rather than 404: a 404 would confirm that some tokens map to
            // something and others do not, which is the distinction this flow
            // exists to hide.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_INVITATION', 'message' => $e->getMessage()],
            ], 422);
        }

        // No session is issued. Setting a password and being signed in are
        // separate acts, and the login flow already handles MFA, device trust
        // and deactivation checks that this endpoint has no business
        // reimplementing.
        return response()->json([
            'success' => true,
            'message' => __('Your account is ready. You can now sign in.'),
        ]);
    }
}
