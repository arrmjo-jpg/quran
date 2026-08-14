<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Presentation\HTTP\Resources\SeasonResource;

final class PublicSeasonController extends Controller
{
    public function __construct(
        private readonly SeasonRepositoryContract $repository,
    ) {}

    public function index(): JsonResponse
    {
        $seasons = $this->repository->findAll();

        return response()->json([
            'success' => true,
            'data' => SeasonResource::collection($seasons),
        ]);
    }

    public function current(): JsonResponse
    {
        $season = $this->repository->findActiveSeason();

        if (! $season) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SEASON',
                    'message' => __('No active competition season currently open.'),
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new SeasonResource($season),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $season = $this->repository->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SeasonResource($season),
        ]);
    }
}
