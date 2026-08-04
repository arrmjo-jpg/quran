<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\CompetitionRuleEngine;
use Modules\Competition\Presentation\HTTP\Requests\CreateSeasonRequest;
use Modules\Competition\Presentation\HTTP\Resources\SeasonResource;

final class AdminSeasonController extends Controller
{
    public function __construct(
        private readonly SeasonRepositoryContract $repository,
        private readonly CompetitionRuleEngine $ruleEngine,
    ) {}

    public function store(CreateSeasonRequest $request): JsonResponse
    {
        $season = Season::create(
            id: fake()->uuid(),
            slug: $request->validated('slug'),
            year: (int) $request->validated('year'),
            regStartIso: $request->validated('registration_start'),
            regEndIso: $request->validated('registration_end'),
            startDateIso: $request->validated('start_date'),
            endDateIso: $request->validated('end_date'),
            translations: [
                'ar' => $request->validated('title_ar'),
                'en' => $request->validated('title_en'),
            ]
        );

        $this->repository->save($season);

        return response()->json([
            'success' => true,
            'message' => __('Season created successfully.'),
            'data' => new SeasonResource($season),
        ], 201);
    }

    public function openRegistration(string $id): JsonResponse
    {
        $season = $this->repository->findOrFail($id);

        try {
            $season->openRegistration();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        }

        $this->repository->save($season);

        return response()->json([
            'success' => true,
            'message' => __('Registration opened successfully for season.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function closeRegistration(string $id): JsonResponse
    {
        $season = $this->repository->findOrFail($id);

        try {
            $season->closeRegistration();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        }

        $this->repository->save($season);

        return response()->json([
            'success' => true,
            'message' => __('Registration closed successfully for season.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function previewResults(string $stageId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'stage_id' => $stageId,
                'status' => 'preview',
                'qualified' => [],
                'waitlist' => [],
                'eliminated' => [],
                'total_scored' => 0,
            ],
        ]);
    }

    public function simulateRanking(Request $request, string $stageId): JsonResponse
    {
        $scores = $request->input('scores', []);
        $ranked = $this->ruleEngine->breakTies($scores);

        return response()->json([
            'success' => true,
            'message' => __('Ranking dry run simulation executed successfully with zero DB persistence.'),
            'data' => [
                'stage_id' => $stageId,
                'simulation' => $ranked,
            ],
        ]);
    }
}
