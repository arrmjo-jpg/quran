<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Competition\Domain\Repositories\JudgeScoreSystemRepositoryContract;
use Modules\Competition\Domain\Repositories\ParticipationTypeRepositoryContract;
use Modules\Competition\Domain\Repositories\TajweedLevelRepositoryContract;
use Modules\Competition\Presentation\HTTP\Resources\LookupOptionResource;

/**
 * AdminLookupController
 *
 * The catalogs behind the admin form pickers. The season rules endpoint
 * takes participation_type_id and tajweed_level_id, and the stage rules
 * endpoint takes judge_score_system_id, but until now nothing could list
 * what those ids may be — the write endpoints existed with no way for an
 * admin to choose a value for them.
 *
 * Read-only and deliberately trivial: repository straight to resource, no
 * Use Case, no transaction, nothing to coordinate. Adding an application
 * layer here would be ceremony around a SELECT.
 *
 * Countries are absent on purpose — the Countries module already publishes
 * its own catalog at GET /api/v1/countries.
 */
final class AdminLookupController extends Controller
{
    public function __construct(
        private readonly ParticipationTypeRepositoryContract $participationTypes,
        private readonly TajweedLevelRepositoryContract $tajweedLevels,
        private readonly JudgeScoreSystemRepositoryContract $judgeScoreSystems,
    ) {}

    public function participationTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookupOptionResource::collection($this->participationTypes->findAllActive()),
        ]);
    }

    public function tajweedLevels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookupOptionResource::collection($this->tajweedLevels->findAllActive()),
        ]);
    }

    public function judgeScoreSystems(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookupOptionResource::collection($this->judgeScoreSystems->findAllActive()),
        ]);
    }
}
