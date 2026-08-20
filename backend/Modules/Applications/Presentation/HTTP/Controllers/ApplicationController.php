<?php

declare(strict_types=1);

namespace Modules\Applications\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Applications\Application\UseCases\SubmitApplicationUseCase;
use Modules\Applications\Domain\Exceptions\ActiveMembershipRequiredException;
use Modules\Applications\Domain\Exceptions\ContestantProfileRequiredException;
use Modules\Applications\Domain\Exceptions\DuplicateApplicationException;
use Modules\Applications\Domain\Repositories\ApplicationRepositoryContract;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Applications\Presentation\HTTP\Requests\RequestReuploadRequest;
use Modules\Applications\Presentation\HTTP\Requests\SubmitApplicationRequest;
use Modules\Applications\Presentation\HTTP\Resources\ApplicationResource;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;

final class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationRepositoryContract $repository,
        private readonly ContestantRepositoryContract $contestantRepository,
    ) {}

    public function submit(
        SubmitApplicationRequest $request,
        SubmitApplicationUseCase $submitApplication,
    ): JsonResponse {
        try {
            $application = $submitApplication->execute(
                userId: (string) $request->user()->id,
                seasonId: $request->validated('season_id'),
                stageId: $request->validated('stage_id'),
                videoMediaId: $request->validated('video_media_asset_id'),
            );
        } catch (ContestantProfileRequiredException) {
            // 422 and this wording are what the endpoint already answered. The
            // extraction is not the place to improve either.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PROFILE_REQUIRED', 'message' => __('Create contestant profile first.')],
            ], 422);
        } catch (ActiveMembershipRequiredException) {
            // 422 rather than 409, matching PROFILE_REQUIRED above: both say
            // the applicant is not set up to apply, which is a fact about them
            // and not a conflict with an existing application. The two
            // refusals are the same shape and answer the same way.
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBERSHIP_REQUIRED',
                    'message' => __('Join a circle before submitting an application.'),
                ],
            ], 422);
        } catch (DuplicateApplicationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_APPLICATION',
                    'message' => __('An application already exists for this contestant, season, and stage.'),
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Application submitted successfully.'),
            'data' => new ApplicationResource($application),
        ], 201);
    }

    public function myApplications(Request $request): JsonResponse
    {
        $contestant = $this->contestantRepository->findByUserId($request->user()->id);

        if (! $contestant) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $applications = ApplicationModel::query()
            ->where('contestant_id', (string) $contestant->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => ApplicationResource::collection($applications),
        ]);
    }

    public function indexAdmin(Request $request): JsonResponse
    {
        $query = ApplicationModel::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ApplicationResource::collection($paginator->items()),
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

    public function requestReupload(RequestReuploadRequest $request, string $id): JsonResponse
    {
        $application = $this->repository->findOrFail($id);
        $application->requestReupload($request->validated('reason'));
        $this->repository->save($application);

        return response()->json([
            'success' => true,
            'message' => __('Reupload requested successfully.'),
            'data' => new ApplicationResource($application),
        ]);
    }

    public function markReadyForJudging(string $id): JsonResponse
    {
        $application = $this->repository->findOrFail($id);
        $application->markReadyForJudging();
        $this->repository->save($application);

        return response()->json([
            'success' => true,
            'message' => __('Application marked ready for judging.'),
            'data' => new ApplicationResource($application),
        ]);
    }
}
