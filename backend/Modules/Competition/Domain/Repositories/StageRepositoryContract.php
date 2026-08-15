<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\Entities\Stage;

interface StageRepositoryContract
{
    public function findOrFail(string $id): Stage;

    public function find(string $id): ?Stage;

    /**
     * A season's stages in competition order (stage_number ascending).
     *
     * @return array<int, Stage>
     */
    public function findBySeason(string $seasonId): array;

    /**
     * The stage_number a newly appended stage should take: one past the
     * season's current highest, or 1 for the season's first stage.
     * Stage numbers are server-assigned on create and only ever changed
     * through applyOrder() — never supplied by a client — so that
     * uk_stages_season_number can only ever be contended in one place.
     */
    public function nextStageNumber(string $seasonId): int;

    public function save(Stage $stage): void;

    /**
     * Hard delete, translations included. Callers are responsible for the
     * policy guards (draft season, not referenced) — this only performs
     * the write.
     */
    public function delete(string $id): void;

    /**
     * Whether anything outside the stage's own translations points at it:
     * season stage rules, judge assignments, streams, applications, or
     * stage results. A referenced stage can never be deleted.
     */
    public function isReferenced(string $stageId): bool;

    /**
     * Renumber a season's stages to 1..N in the given id order. Writing
     * these one-by-one would transiently duplicate a stage_number and trip
     * uk_stages_season_number, so implementations must stage the write
     * (see StageRepository) rather than assigning final numbers directly.
     *
     * @param  array<int, string>  $orderedStageIds  every stage of the season, in the desired order
     */
    public function applyOrder(string $seasonId, array $orderedStageIds): void;
}
