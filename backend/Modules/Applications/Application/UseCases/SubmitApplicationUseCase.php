<?php

declare(strict_types=1);

namespace Modules\Applications\Application\UseCases;

use Modules\Applications\Domain\Entities\Application;
use Modules\Applications\Domain\Exceptions\ActiveMembershipRequiredException;
use Modules\Applications\Domain\Exceptions\ContestantProfileRequiredException;
use Modules\Applications\Domain\Exceptions\DuplicateApplicationException;
use Illuminate\Support\Facades\DB;
use Modules\Applications\Domain\Repositories\ApplicationRepositoryContract;
use Modules\Applications\Domain\ValueObjects\PlacementSnapshot;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Symfony\Component\Uid\Uuid;

/**
 * A contestant submits an application to a stage of a season.
 *
 * EXTRACTED FROM ApplicationController::submit() WITHOUT CHANGING WHAT IT
 * DOES. ADR-016 Q4 names this class as the home of the active-membership rule,
 * and it did not exist — the logic sat inline in a controller, which is why
 * `Modules/Applications/Application/UseCases` was an empty directory. The rule
 * cannot be added to a class that is not there, so the move comes first and
 * alone.
 *
 * THREE THINGS ARE STILL DELIBERATELY MISSING, each pinned by the golden
 * master, each to be added in its own later commit:
 *
 *   1. No event dispatch. Application::submit() records ApplicationSubmitted
 *      and nothing releases it. Every other use case in this codebase ends
 *      with a dispatch loop, and its absence here will look like an oversight
 *      to anyone reading this file — it is not. Adding it changes behaviour.
 *   2. No persistence of submitted_at, which the repository drops.
 *   3. No handling of `notes`, which the request validates and discards.
 *
 * G3 — THE ACTIVE MEMBERSHIP RULE — LIVES HERE, which is what this class was
 * extracted for. It is checked before the duplicate lookup: whether a
 * contestant may apply at all precedes whether they already have, and a
 * contestant with neither a membership nor a prior application should be told
 * the thing they can act on. The read goes through Organization's Domain
 * contract, so this module stays unaware of how memberships are stored.
 *
 * D8 — THE PLACEMENT SNAPSHOT — ALSO LIVES HERE, and it depends on G3 having
 * run first: the membership is what says which circle to freeze. The circle
 * and the centre are read at submission time and copied onto the application,
 * because Q3 makes a circle's location its centre's, so an id resolves to
 * whatever that centre is called now rather than what it was called then.
 *
 * THE TRANSACTION ARRIVES WITH D8, exactly where the extraction commit said it
 * would. It is no longer one `updateOrCreate` that cannot be half-applied:
 * three reads now decide what gets written, and without a transaction a circle
 * renamed between the read and the write would be frozen under a name that was
 * never simultaneously true.
 */
final class SubmitApplicationUseCase
{
    public function __construct(
        private ApplicationRepositoryContract $applications,
        private ContestantRepositoryContract $contestants,
        private ContestantMembershipRepositoryContract $memberships,
        private CircleRepositoryContract $circles,
        private CenterRepositoryContract $centers,
    ) {}

    public function execute(
        string $userId,
        string $seasonId,
        string $stageId,
        string $videoMediaId,
    ): Application {
        return DB::transaction(function () use ($userId, $seasonId, $stageId, $videoMediaId): Application {
            $contestant = $this->contestants->findByUserId($userId);

            if (! $contestant) {
                throw ContestantProfileRequiredException::forUser($userId);
            }

            $contestantId = (string) $contestant->id;

            $membership = $this->memberships->findActiveForContestant($contestantId);

            if ($membership === null) {
                throw ActiveMembershipRequiredException::forContestant($contestantId);
            }

            if ($this->applications->findByContestantSeasonStage($contestantId, $seasonId, $stageId)) {
                throw DuplicateApplicationException::for($contestantId, $seasonId, $stageId);
            }

            $application = Application::submit(
                id: (string) Uuid::v7(),
                contestantId: $contestantId,
                seasonId: $seasonId,
                stageId: $stageId,
                videoMediaId: $videoMediaId,
                placement: $this->freezePlacement($membership->getCircleId()),
            );

            $this->applications->save($application);

            return $application;
        });
    }

    /**
     * Copies the contestant's current placement onto the application.
     *
     * Two reads rather than one, because a circle stores no location of its
     * own (Q3): the circle names itself and points at a centre, and only the
     * centre can say what it is called. That indirection is the whole reason
     * D8 freezes names as well as ids.
     */
    private function freezePlacement(string $circleId): PlacementSnapshot
    {
        $circle = $this->circles->findOrFail(new CircleId($circleId));
        $center = $this->centers->findOrFail(new CenterId($circle->getCenterId()));

        return new PlacementSnapshot(
            centerId: $circle->getCenterId(),
            circleId: $circleId,
            centerName: $center->getName(),
            circleName: $circle->getName(),
        );
    }
}
