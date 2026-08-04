<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\Services\EligibilityService;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Contestants\Presentation\HTTP\Requests\UpdateContestantProfileRequest;
use Modules\Contestants\Presentation\HTTP\Resources\ContestantPrivateResource;

final class ContestantController extends Controller
{
    public function __construct(
        private readonly ContestantRepositoryContract $repository,
        private readonly EligibilityService $eligibilityService,
    ) {}

    public function showProfile(Request $request): JsonResponse
    {
        $contestant = $this->repository->findByUserId($request->user()->id);

        if (! $contestant) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROFILE_NOT_FOUND',
                    'message' => __('Contestant profile has not been created yet.'),
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }

    public function updateProfile(UpdateContestantProfileRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $contestant = $this->repository->findByUserId($userId);

        if (! $contestant) {
            $contestant = Contestant::create(
                id: ContestantId::generate(),
                userId: $userId,
                countryId: $request->validated('country_id'),
                fullName: $request->validated('full_name'),
                dateOfBirth: new BirthDate($request->validated('date_of_birth')),
                gender: new Gender($request->validated('gender')),
                phoneNumber: $request->validated('phone_number'),
                nationalId: $request->validated('national_id'),
                photoMediaId: $request->validated('photo_media_asset_id')
            );
        } else {
            $contestant->updateProfile(
                fullName: $request->validated('full_name'),
                phoneNumber: $request->validated('phone_number'),
                photoMediaId: $request->validated('photo_media_asset_id')
            );
        }

        $this->repository->save($contestant);

        return response()->json([
            'success' => true,
            'message' => __('Contestant profile updated successfully.'),
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }

    public function checkEligibility(Request $request): JsonResponse
    {
        $contestant = $this->repository->findByUserId($request->user()->id);

        if (! $contestant) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PROFILE_REQUIRED', 'message' => __('Complete contestant profile first.')],
            ], 422);
        }

        $result = $this->eligibilityService->checkEligibility($contestant, '2026-08-01 00:00:00');

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
