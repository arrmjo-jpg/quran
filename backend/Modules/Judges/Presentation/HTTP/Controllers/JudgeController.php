<?php

declare(strict_types=1);

namespace Modules\Judges\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Judges\Domain\Entities\Judge;
use Modules\Judges\Domain\Repositories\JudgeRepositoryContract;
use Modules\Judges\Infrastructure\Database\Models\JudgeModel;
use Modules\Judges\Presentation\HTTP\Requests\CreateJudgeRequest;
use Modules\Judges\Presentation\HTTP\Resources\JudgeResource;

final class JudgeController extends Controller
{
    public function __construct(
        private readonly JudgeRepositoryContract $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = JudgeModel::query();

        if ($spec = $request->query('specialization')) {
            $query->where('specialization', $spec);
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => JudgeResource::collection($paginator->items()),
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

    public function store(CreateJudgeRequest $request): JsonResponse
    {
        $judge = Judge::create(
            id: fake()->uuid(),
            userId: $request->validated('user_id'),
            fullName: $request->validated('full_name'),
            specialization: $request->validated('specialization'),
            title: $request->validated('title'),
            bio: $request->validated('bio')
        );

        $this->repository->save($judge);

        return response()->json([
            'success' => true,
            'message' => __('Judge profile created successfully.'),
            'data' => new JudgeResource($judge),
        ], 201);
    }

    public function profile(Request $request): JsonResponse
    {
        $judge = $this->repository->findByUserId($request->user()->id);

        if (! $judge) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_A_JUDGE', 'message' => __('Authenticated user is not an assigned judge.')],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new JudgeResource($judge),
        ]);
    }
}
