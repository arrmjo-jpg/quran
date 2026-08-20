<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Repositories;

use Modules\Organization\Domain\Entities\Circle;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\CircleId;

interface CircleRepositoryContract
{
    public function find(CircleId $id): ?Circle;

    public function findOrFail(CircleId $id): Circle;

    /**
     * Whether another circle in the same centre already carries this name.
     *
     * Scoped to the centre: "the women's circle" in every centre in the
     * country is ordinary, two of them in one centre is the ambiguity worth
     * refusing.
     *
     * Takes the id to exclude so renaming a circle to the name it already has
     * is not reported as a collision with itself.
     */
    public function nameTakenInCenter(string $centerId, string $name, ?CircleId $excluding = null): bool;

    /**
     * How many circles a centre currently holds.
     *
     * Exists for DeleteCenterUseCase's refusal, which is a rule the Center
     * aggregate cannot enforce: it needs a count no aggregate can take of
     * itself. Soft-deleted circles are not counted — a closed circle does not
     * keep a centre open.
     */
    public function countInCenter(CenterId $centerId): int;

    public function save(Circle $circle): void;

    /**
     * Soft delete. Applications freeze a circle's name (D8) and a hard delete
     * would leave those records pointing at nothing.
     */
    public function delete(CircleId $id): void;
}
