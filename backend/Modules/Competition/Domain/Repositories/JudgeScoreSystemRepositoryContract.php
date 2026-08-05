<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Repositories;

use Modules\Competition\Domain\ValueObjects\ResolvedJudgeScoreSystem;

interface JudgeScoreSystemRepositoryContract
{
    public function findOrFail(string $id): ResolvedJudgeScoreSystem;
}
