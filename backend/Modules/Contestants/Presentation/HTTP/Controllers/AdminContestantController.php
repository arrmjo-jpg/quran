<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Presentation\HTTP\Resources\ContestantPrivateResource;

final class AdminContestantController extends Controller
{
    public function __construct(
        private readonly ContestantRepositoryContract $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contestants = $this->repository->search($request->query('q'));

        return response()->json([
            'success' => true,
            'data' => ContestantPrivateResource::collection($contestants),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $contestant = $this->repository->findOrFail(new ContestantId($id));
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_CONTESTANT_ID', 'message' => __('Invalid contestant id format.')],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new ContestantPrivateResource($contestant),
        ]);
    }
}
