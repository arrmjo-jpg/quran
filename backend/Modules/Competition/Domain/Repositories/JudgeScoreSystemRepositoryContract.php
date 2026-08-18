<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedJudgeScoreSystem;

interface JudgeScoreSystemRepositoryContract
{
    public function findOrFail(string $id): ResolvedJudgeScoreSystem;

    /**
     * The selectable catalog, active rows only, in display_order. Backs the
     * admin picker that supplies judge_score_system_id per stage rule —
     * which is why this returns the richer type: the picker shows each
     * scale's ceiling (out of 100, out of 50, ...), and that ceiling is
     * what a qualification percentage is later resolved against.
     *
     * @return array<int, ResolvedJudgeScoreSystem>
     */
    public function findAllActive(): array;
}
