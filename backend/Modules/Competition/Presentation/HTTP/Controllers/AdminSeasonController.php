<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Competition\Application\UseCases\CloseSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\CreateSeasonUseCase;
use Modules\Competition\Application\UseCases\OpenSeasonRegistrationUseCase;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Services\CompetitionRuleEngine;
use Modules\Competition\Presentation\HTTP\Requests\CreateSeasonRequest;
use Modules\Competition\Presentation\HTTP\Resources\SeasonResource;
use Symfony\Component\Uid\Uuid;

final class AdminSeasonController extends Controller
{
    public function __construct(
        private readonly CreateSeasonUseCase $createSeason,
        private readonly OpenSeasonRegistrationUseCase $openSeasonRegistration,
        private readonly CloseSeasonRegistrationUseCase $closeSeasonRegistration,
        private readonly CompetitionRuleEngine $ruleEngine,
    ) {}

    public function store(CreateSeasonRequest $request): JsonResponse
    {
        $season = $this->createSeason->execute(
            id: (string) Uuid::v7(),
            slug: $request->validated('slug'),
            year: (int) $request->validated('year'),
            registrationStartIso: $request->validated('registration_start'),
            registrationEndIso: $request->validated('registration_end'),
            startDateIso: $request->validated('start_date'),
            endDateIso: $request->validated('end_date'),
            translations: [
                'ar' => ['title' => $request->validated('title_ar'), 'public_name' => $request->validated('public_name_ar')],
                'en' => ['title' => $request->validated('title_en'), 'public_name' => $request->validated('public_name_en')],
                'es' => ['title' => $request->validated('title_es'), 'public_name' => $request->validated('public_name_es')],
            ],
        );

        return response()->json([
            'success' => true,
            'message' => __('Season created successfully.'),
            'data' => new SeasonResource($season),
        ], 201);
    }

    public function openRegistration(string $id, Request $request): JsonResponse
    {
        try {
            $season = $this->openSeasonRegistration->execute($id, $request->user()?->id);
        } catch (InvalidSeasonTransitionException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        } catch (IncompleteSeasonRulesException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INCOMPLETE_SEASON_RULES', 'message' => $e->getMessage(), 'missing' => $e->missing],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Registration opened successfully for season.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function closeRegistration(string $id): JsonResponse
    {
        try {
            $season = $this->closeSeasonRegistration->execute($id);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        }

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
