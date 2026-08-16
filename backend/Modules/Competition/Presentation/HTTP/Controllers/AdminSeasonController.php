<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Competition\Application\UseCases\ArchiveSeasonUseCase;
use Modules\Competition\Application\UseCases\CancelSeasonUseCase;
use Modules\Competition\Application\UseCases\CloseSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\CreateSeasonUseCase;
use Modules\Competition\Application\UseCases\OpenSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\ReopenSeasonRegistrationUseCase;
use Modules\Competition\Application\UseCases\UpdateSeasonRulesUseCase;
use Modules\Competition\Application\UseCases\UpdateSeasonUseCase;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Exceptions\SeasonNotReopenableException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\CompetitionRuleEngine;
use Modules\Competition\Presentation\HTTP\Requests\ArchiveSeasonRequest;
use Modules\Competition\Presentation\HTTP\Requests\CancelSeasonRequest;
use Modules\Competition\Presentation\HTTP\Requests\CreateSeasonRequest;
use Modules\Competition\Presentation\HTTP\Requests\ReopenRegistrationRequest;
use Modules\Competition\Presentation\HTTP\Requests\UpdateSeasonRequest;
use Modules\Competition\Presentation\HTTP\Requests\UpdateSeasonRulesRequest;
use Modules\Competition\Presentation\HTTP\Resources\SeasonResource;
use Symfony\Component\Uid\Uuid;

final class AdminSeasonController extends Controller
{
    public function __construct(
        private readonly CreateSeasonUseCase $createSeason,
        private readonly OpenSeasonRegistrationUseCase $openSeasonRegistration,
        private readonly CloseSeasonRegistrationUseCase $closeSeasonRegistration,
        private readonly UpdateSeasonRulesUseCase $updateSeasonRules,
        private readonly UpdateSeasonUseCase $updateSeason,
        private readonly ArchiveSeasonUseCase $archiveSeason,
        private readonly CancelSeasonUseCase $cancelSeason,
        private readonly ReopenSeasonRegistrationUseCase $reopenSeasonRegistration,
        private readonly CompetitionRuleEngine $ruleEngine,
        private readonly SeasonRepositoryContract $seasons,
    ) {}

    /**
     * The admin projection of a season. Separate from the public routes
     * because those now return PublicSeasonResource, which deliberately
     * withholds the judging configuration and the archive metadata an
     * admin screen needs.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SeasonResource::collection($this->seasons->findAll()),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new SeasonResource($this->seasons->findOrFail($id)),
        ]);
    }

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

    /**
     * Reopen a closed registration window. Refusals are 409s carrying the
     * specific code — the season is not in registration_closed, or another
     * season already holds the single active slot, in which case the
     * response names it so the admin knows what to close first.
     */
    public function reopenRegistration(string $id, ReopenRegistrationRequest $request): JsonResponse
    {
        try {
            $season = $this->reopenSeasonRegistration->execute(
                $id,
                $request->validated('reason'),
                $request->user()?->id,
            );
        } catch (SeasonNotReopenableException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->reason,
                    'message' => $e->getMessage(),
                    // Identified well enough to act on without a lookup.
                    'active_season_id' => $e->activeSeasonId,
                    'active_season_slug' => $e->activeSeasonSlug,
                    'active_season_year' => $e->activeSeasonYear,
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Season registration reopened successfully.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function update(string $id, UpdateSeasonRequest $request): JsonResponse
    {
        try {
            $season = $this->updateSeason->execute(
                seasonId: $id,
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
        } catch (SeasonAlreadyFrozenException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SEASON_ALREADY_FROZEN', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Season updated successfully.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function archive(string $id, ArchiveSeasonRequest $request): JsonResponse
    {
        try {
            $season = $this->archiveSeason->execute($id, $request->validated('reason'), $request->user()?->id);
        } catch (InvalidSeasonTransitionException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Season archived successfully.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function cancel(string $id, CancelSeasonRequest $request): JsonResponse
    {
        try {
            $season = $this->cancelSeason->execute($id, $request->validated('reason'), $request->user()?->id);
        } catch (InvalidSeasonTransitionException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STATE_TRANSITION', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Season cancelled successfully.'),
            'data' => new SeasonResource($season),
        ]);
    }

    public function updateRules(string $id, UpdateSeasonRulesRequest $request): JsonResponse
    {
        try {
            $season = $this->updateSeasonRules->execute(
                seasonId: $id,
                minAge: (int) $request->validated('min_age'),
                maxAge: (int) $request->validated('max_age'),
                participationTypeId: $request->validated('participation_type_id'),
                tajweedLevelId: $request->validated('tajweed_level_id'),
                countryIds: $request->validated('country_ids'),
            );
        } catch (SeasonAlreadyFrozenException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SEASON_ALREADY_FROZEN', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Season rules updated successfully.'),
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
