<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Organization\Application\UseCases\EndMembershipUseCase;
use Modules\Organization\Application\UseCases\StartMembershipUseCase;
use Modules\Organization\Application\UseCases\TransferMembershipUseCase;
use Modules\Organization\Infrastructure\Database\Models\ContestantMembershipModel;
use Modules\Organization\Presentation\HTTP\Requests\EndMembershipRequest;
use Modules\Organization\Presentation\HTTP\Requests\StartMembershipRequest;
use Modules\Organization\Presentation\HTTP\Requests\TransferMembershipRequest;
use Modules\Organization\Presentation\HTTP\Resources\MembershipResource;

/**
 * Membership administration — ADR-016 Q4.
 *
 * Every write goes through a use case; this translates domain refusals into
 * status codes and adds no rule of its own.
 */
final class MembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContestantMembershipModel::query()->with('circle');

        if ($contestantId = $request->query('contestant_id')) {
            $query->where('contestant_id', $contestantId);
        }

        if ($circleId = $request->query('circle_id')) {
            $query->where('circle_id', $circleId);
        }

        // Defaults to everything rather than to open memberships. A history
        // that hides its closed rows by default is a history nobody sees, and
        // Q4 made this table historical on purpose. Callers wanting a current
        // roster ask for one.
        if ($request->has('active')) {
            $request->boolean('active')
                ? $query->whereNull('left_at')
                : $query->whereNotNull('left_at');
        }

        $paginator = $query
            ->orderByDesc('joined_at')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'data' => MembershipResource::collection($paginator->items()),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MembershipResource(
                ContestantMembershipModel::query()->with('circle')->findOrFail($id)
            ),
        ]);
    }

    public function store(StartMembershipRequest $request, StartMembershipUseCase $start): JsonResponse
    {
        try {
            $membership = $start->execute(
                contestantId: $request->validated('contestant_id'),
                circleId: $request->validated('circle_id'),
                joinedAt: $request->validated('joined_at'),
            );
        } catch (DomainException $e) {
            return $this->refusal('ALREADY_ENROLLED', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_MEMBERSHIP', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contestant enrolled.'),
            'data' => new MembershipResource(
                ContestantMembershipModel::query()->with('circle')->findOrFail($membership->id->value)
            ),
        ], 201);
    }

    public function end(EndMembershipRequest $request, string $id, EndMembershipUseCase $endMembership): JsonResponse
    {
        try {
            $endMembership->execute(
                membershipId: $id,
                reason: $request->validated('reason'),
                leftAt: $request->validated('left_at'),
            );
        } catch (DomainException $e) {
            return $this->refusal('MEMBERSHIP_ALREADY_ENDED', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_MEMBERSHIP', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Membership ended.'),
            'data' => new MembershipResource(
                ContestantMembershipModel::query()->with('circle')->findOrFail($id)
            ),
        ]);
    }

    public function transfer(TransferMembershipRequest $request, TransferMembershipUseCase $transfer): JsonResponse
    {
        try {
            $membership = $transfer->execute(
                contestantId: $request->validated('contestant_id'),
                toCircleId: $request->validated('to_circle_id'),
                reason: $request->validated('reason'),
                at: $request->validated('at'),
            );
        } catch (DomainException $e) {
            return $this->refusal('TRANSFER_REFUSED', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_MEMBERSHIP', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contestant transferred.'),
            'data' => new MembershipResource(
                ContestantMembershipModel::query()->with('circle')->findOrFail($membership->id->value)
            ),
        ], 201);
    }

    private function refusal(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
