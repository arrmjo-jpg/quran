<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\Repositories;

use Modules\Evaluations\Domain\Entities\Evaluation;

interface EvaluationRepositoryContract
{
    public function findOrFail(string $id): Evaluation;

    public function findByAppAndJudge(string $appId, string $judgeId): ?Evaluation;

    public function findByJudge(string $judgeId, ?string $status = null): array;

    public function countSubmittedByApplication(string $applicationId): int;

    public function findAllByApplication(string $applicationId): array;

    public function save(Evaluation $evaluation): void;
}
