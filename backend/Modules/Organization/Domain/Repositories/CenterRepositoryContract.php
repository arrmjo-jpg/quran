<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Repositories;

use Modules\Organization\Domain\Entities\Center;
use Modules\Organization\Domain\ValueObjects\CenterId;

interface CenterRepositoryContract
{
    public function find(CenterId $id): ?Center;

    public function findOrFail(CenterId $id): Center;

    /**
     * Whether another centre in the same city already carries this name.
     *
     * Scoped to the city rather than the country: one "Central Centre" per
     * town is ordinary, two in the same town is the ambiguity worth refusing.
     *
     * Takes the id to exclude so renaming a centre to the name it already has
     * is not reported as a collision with itself.
     */
    public function nameTakenInCity(string $countryId, string $city, string $name, ?CenterId $excluding = null): bool;

    public function save(Center $center): void;

    /**
     * Soft delete. Centres are never destroyed: applications froze their names
     * and a hard delete would leave those records pointing at nothing.
     */
    public function delete(CenterId $id): void;
}
