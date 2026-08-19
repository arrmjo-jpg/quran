<?php

declare(strict_types=1);

namespace Modules\Applications\Application\UseCases;

use Modules\Applications\Domain\Entities\Application;
use Modules\Applications\Domain\Exceptions\ContestantProfileRequiredException;
use Modules\Applications\Domain\Exceptions\DuplicateApplicationException;
use Modules\Applications\Domain\Repositories\ApplicationRepositoryContract;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
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
 * FOUR THINGS ARE DELIBERATELY MISSING, each pinned by the golden master that
 * landed in the previous commit, each to be added in its own later commit:
 *
 *   1. No event dispatch. Application::submit() records ApplicationSubmitted
 *      and nothing releases it. Every other use case in this codebase ends
 *      with a dispatch loop, and its absence here will look like an oversight
 *      to anyone reading this file — it is not. Adding it changes behaviour,
 *      and this commit changes none.
 *   2. No persistence of submitted_at, which the repository drops.
 *   3. No handling of `notes`, which the request validates and discards.
 *   4. No membership check. That is G3, and it is the reason this class now
 *      exists.
 *
 * NO TRANSACTION EITHER, for the same reason. One `updateOrCreate` cannot be
 * partially applied, so wrapping it would be unobservable today — but it stops
 * being unobservable the moment G3 adds a read and D8 adds more to write, and
 * it belongs in the commit where it starts to matter rather than smuggled in
 * here.
 */
final class SubmitApplicationUseCase
{
    public function __construct(
        private ApplicationRepositoryContract $applications,
        private ContestantRepositoryContract $contestants,
    ) {}

    public function execute(
        string $userId,
        string $seasonId,
        string $stageId,
        string $videoMediaId,
    ): Application {
        $contestant = $this->contestants->findByUserId($userId);

        if (! $contestant) {
            throw ContestantProfileRequiredException::forUser($userId);
        }

        $contestantId = (string) $contestant->id;

        if ($this->applications->findByContestantSeasonStage($contestantId, $seasonId, $stageId)) {
            throw DuplicateApplicationException::for($contestantId, $seasonId, $stageId);
        }

        $application = Application::submit(
            id: (string) Uuid::v7(),
            contestantId: $contestantId,
            seasonId: $seasonId,
            stageId: $stageId,
            videoMediaId: $videoMediaId,
        );

        $this->applications->save($application);

        return $application;
    }
}
