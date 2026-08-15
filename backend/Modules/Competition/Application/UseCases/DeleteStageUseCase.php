<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Competition\Domain\Exceptions\StageInUseException;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\Services\SeasonEditingPolicy;

/**
 * DeleteStageUseCase
 *
 * Hard-deletes a stage, and only ever an unused one. Two guards, both
 * required:
 *
 *  - the season must still be editable (not frozen), and
 *  - nothing may reference the stage yet — no season stage rule, judge
 *    assignment, stream, application, or stage result.
 *
 * A referenced stage is not deletable later either: there is no soft
 * delete and no archive flag to fall back on, by decision. The referencing
 * FKs are all RESTRICT, so the database would refuse the write anyway —
 * isReferenced() exists so the caller gets a 409 explaining why instead of
 * a raw integrity-constraint error.
 *
 * Deleting a stage leaves the remaining stage_numbers with a gap (1, 3, 4
 * after deleting 2). That is intentional: renumbering is an explicit
 * admin action through ReorderStagesUseCase, not a hidden side effect of
 * a delete.
 */
final readonly class DeleteStageUseCase
{
    public function __construct(
        private StageRepositoryContract $stages,
        private SeasonRepositoryContract $seasons,
        private SeasonEditingPolicy $editing,
    ) {}

    public function execute(string $stageId): void
    {
        DB::transaction(function () use ($stageId): void {
            $stage = $this->stages->findOrFail($stageId);

            $this->editing->assertEditable($this->seasons->findOrFail($stage->seasonId), 'stages');

            if ($this->stages->isReferenced($stageId)) {
                throw new StageInUseException($stageId);
            }

            $this->stages->delete($stageId);
        });
    }
}
