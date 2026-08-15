<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Competition\Application\UseCases\CreateStageUseCase;
use Modules\Competition\Application\UseCases\DeleteStageUseCase;
use Modules\Competition\Application\UseCases\ReorderStagesUseCase;
use Modules\Competition\Application\UseCases\UpdateSeasonStageRulesUseCase;
use Modules\Competition\Application\UseCases\UpdateStageUseCase;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Exceptions\StageInUseException;
use Modules\Competition\Domain\Repositories\SeasonStageRuleRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\ValueObjects\StageRuleAssignment;
use Modules\Competition\Presentation\HTTP\Requests\CreateStageRequest;
use Modules\Competition\Presentation\HTTP\Requests\ReorderStagesRequest;
use Modules\Competition\Presentation\HTTP\Requests\UpdateSeasonStageRulesRequest;
use Modules\Competition\Presentation\HTTP\Requests\UpdateStageRequest;
use Modules\Competition\Presentation\HTTP\Resources\StageResource;
use Modules\Competition\Presentation\HTTP\Resources\StageRuleResource;
use Symfony\Component\Uid\Uuid;

final class AdminStageController extends Controller
{
    public function __construct(
        private readonly CreateStageUseCase $createStage,
        private readonly UpdateStageUseCase $updateStage,
        private readonly DeleteStageUseCase $deleteStage,
        private readonly ReorderStagesUseCase $reorderStages,
        private readonly UpdateSeasonStageRulesUseCase $updateStageRules,
        private readonly StageRepositoryContract $stages,
        private readonly SeasonStageRuleRepositoryContract $stageRules,
    ) {}

    public function index(string $seasonId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StageResource::collection($this->stages->findBySeason($seasonId)),
        ]);
    }

    public function store(string $seasonId, CreateStageRequest $request): JsonResponse
    {
        try {
            $stage = $this->createStage->execute(
                id: (string) Uuid::v7(),
                seasonId: $seasonId,
                type: $request->validated('type'),
                startDateIso: $request->validated('start_date'),
                endDateIso: $request->validated('end_date'),
                evaluationTemplateId: $request->validated('evaluation_template_id'),
                translations: $request->validated('translations'),
            );
        } catch (SeasonAlreadyFrozenException $e) {
            return $this->frozen($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Stage created successfully.'),
            'data' => new StageResource($stage),
        ], 201);
    }

    public function update(string $id, UpdateStageRequest $request): JsonResponse
    {
        try {
            $stage = $this->updateStage->execute(
                stageId: $id,
                type: $request->validated('type'),
                startDateIso: $request->validated('start_date'),
                endDateIso: $request->validated('end_date'),
                evaluationTemplateId: $request->validated('evaluation_template_id'),
                translations: $request->validated('translations') ?? [],
            );
        } catch (SeasonAlreadyFrozenException $e) {
            return $this->frozen($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Stage updated successfully.'),
            'data' => new StageResource($stage),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->deleteStage->execute($id);
        } catch (SeasonAlreadyFrozenException $e) {
            return $this->frozen($e);
        } catch (StageInUseException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'STAGE_IN_USE', 'message' => $e->getMessage()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Stage deleted successfully.'),
        ]);
    }

    public function reorder(string $seasonId, ReorderStagesRequest $request): JsonResponse
    {
        try {
            $stages = $this->reorderStages->execute($seasonId, $request->validated('stage_ids'));
        } catch (SeasonAlreadyFrozenException $e) {
            return $this->frozen($e);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STAGE_ORDER', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Stages reordered successfully.'),
            'data' => StageResource::collection($stages),
        ]);
    }

    /**
     * The read side of the bulk rules endpoint. An edit screen has to load
     * the set it is about to replace, and GET .../stages alone does not
     * carry each stage's score system or qualification percentage.
     */
    public function stageRules(string $seasonId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StageRuleResource::collection($this->stageRules->findResolvedRules($seasonId)),
        ]);
    }

    public function updateStageRules(string $seasonId, UpdateSeasonStageRulesRequest $request): JsonResponse
    {
        try {
            // Built inside the try on purpose: StageRuleAssignment enforces
            // the 0..100 bound itself, and while the form request already
            // rejects anything outside it, a drift between the two rules
            // must not turn into a 500.
            $assignments = array_map(
                static fn (array $rule): StageRuleAssignment => new StageRuleAssignment(
                    stageId: $rule['stage_id'],
                    judgeScoreSystemId: $rule['judge_score_system_id'],
                    qualificationPercentage: isset($rule['qualification_percentage'])
                        ? (float) $rule['qualification_percentage']
                        : null,
                ),
                $request->validated('rules'),
            );

            $rules = $this->updateStageRules->execute($seasonId, $assignments);
        } catch (SeasonAlreadyFrozenException $e) {
            return $this->frozen($e);
        } catch (IncompleteSeasonRulesException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INCOMPLETE_STAGE_RULES', 'message' => $e->getMessage(), 'missing' => $e->missing],
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STAGE_RULE', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Stage rules updated successfully.'),
            'data' => StageRuleResource::collection($rules),
        ]);
    }

    private function frozen(SeasonAlreadyFrozenException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'SEASON_ALREADY_FROZEN', 'message' => $e->getMessage()],
        ], 409);
    }
}
