<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Contestants\Application\UseCases\CreateContestantUseCase;
use Modules\Contestants\Application\UseCases\DeleteContestantUseCase;
use Modules\Contestants\Application\UseCases\RestoreContestantUseCase;
use Modules\Contestants\Application\UseCases\UpdateContestantUseCase;
use Modules\Contestants\Domain\Exceptions\UserAlreadyHasContestantException;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Presentation\HTTP\Requests\CreateContestantRequest;
use Modules\Contestants\Presentation\HTTP\Requests\ListContestantsRequest;
use Modules\Contestants\Presentation\HTTP\Requests\UpdateContestantRequest;
use Modules\Contestants\Presentation\HTTP\Resources\ContestantListResource;
use Modules\Contestants\Presentation\HTTP\Resources\ContestantPrivateResource;

/**
 * Contestant administration — Epic 4 Story 1 (ADR-016 D15, D17).
 *
 * Every write goes through a use case; this translates refusals into status
 * codes and adds no rule of its own, following CenterController.
 */
final class AdminContestantController extends Controller
{
    public function __construct(
        private readonly ContestantRepositoryContract $repository,
    ) {}

    public function index(ListContestantsRequest $request): JsonResponse
    {
        $page = $this->repository->paginate([
            'search' => $request->searchTerm(),
            'country_id' => $request->validated('country_id'),
            'gender' => $request->validated('gender'),
            'with_deleted' => $request->boolean('with_deleted'),
            'per_page' => (int) ($request->validated('per_page') ?? 20),
            'page' => (int) ($request->validated('page') ?? 1),
        ]);

        return response()->json([
            'success' => true,
            'data' => ContestantListResource::collection($page['items']),
            'meta' => [
                'pagination' => [
                    'current_page' => $page['current_page'],
                    'per_page' => $page['per_page'],
                    'total' => $page['total'],
                    'last_page' => $page['last_page'],
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $contestant = $this->repository->findOrFail(new ContestantId($id));
        } catch (InvalidArgumentException) {
            return $this->refusal('INVALID_CONTESTANT_ID', __('Invalid contestant id format.'), 422);
        }

        return response()->json([
            'success' => true,
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }

    public function store(CreateContestantRequest $request, CreateContestantUseCase $createContestant): JsonResponse
    {
        try {
            $contestant = $createContestant->execute(
                userId: $request->validated('user_id'),
                countryId: $request->validated('country_id'),
                fullName: $request->validated('full_name'),
                dateOfBirth: $request->validated('date_of_birth'),
                gender: $request->validated('gender'),
                phoneNumber: $request->validated('phone_number'),
                nationalId: $request->validated('national_id'),
                photoMediaId: $request->validated('photo_media_id'),
                byUserId: (string) $request->user()->id,
            );
        } catch (UserAlreadyHasContestantException $e) {
            // 409 rather than 422: the request is well formed and the account
            // is real. What refuses it is the state of the world — the person
            // is already registered — which is a conflict, not a bad field.
            return $this->refusal('USER_ALREADY_CONTESTANT', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CONTESTANT', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contestant created.'),
            'data' => new ContestantPrivateResource($contestant),
        ], 201);
    }

    public function update(
        UpdateContestantRequest $request,
        string $id,
        UpdateContestantUseCase $updateContestant,
    ): JsonResponse {
        try {
            new ContestantId($id);
        } catch (InvalidArgumentException) {
            return $this->refusal('INVALID_CONTESTANT_ID', __('Invalid contestant id format.'), 422);
        }

        try {
            $contestant = $updateContestant->execute(
                contestantId: $id,
                changes: $request->validated(),
                byUserId: (string) $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CONTESTANT', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contestant updated.'),
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }

    public function destroy(Request $request, string $id, DeleteContestantUseCase $deleteContestant): JsonResponse
    {
        try {
            new ContestantId($id);
        } catch (InvalidArgumentException) {
            return $this->refusal('INVALID_CONTESTANT_ID', __('Invalid contestant id format.'), 422);
        }

        $deleteContestant->execute($id, (string) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => __('Contestant deleted.'),
        ]);
    }

    public function restore(Request $request, string $id, RestoreContestantUseCase $restoreContestant): JsonResponse
    {
        try {
            new ContestantId($id);
        } catch (InvalidArgumentException) {
            return $this->refusal('INVALID_CONTESTANT_ID', __('Invalid contestant id format.'), 422);
        }

        $contestant = $restoreContestant->execute($id, (string) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => __('Contestant restored.'),
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }

    private function refusal(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
