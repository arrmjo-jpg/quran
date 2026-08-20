<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\Circle;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;

/**
 * Renames a circle, or changes who supervises it.
 *
 * The centre is not among the parameters, and its absence is the decision —
 * see Circle::update(). A circle's location IS its centre's under Q3, so
 * moving one between centres would silently relocate everything that reads
 * through it.
 */
final class UpdateCircleUseCase
{
    public function __construct(
        private CircleRepositoryContract $circles,
    ) {}

    public function execute(
        string $circleId,
        string $name,
        ?string $supervisorUserId = null,
    ): Circle {
        return DB::transaction(function () use ($circleId, $name, $supervisorUserId): Circle {
            $id = new CircleId($circleId);
            $circle = $this->circles->findOrFail($id);

            // Scoped to the circle's own centre, which is the only centre it
            // can be in: unlike a centre relocating between cities, there is
            // no "moving to" case to check against.
            if ($this->circles->nameTakenInCenter($circle->getCenterId(), $name, excluding: $id)) {
                throw new DomainException("A circle named '{$name}' already exists at this centre.");
            }

            $circle->update($name, $supervisorUserId);

            $this->circles->save($circle);

            foreach ($circle->releaseEvents() as $event) {
                event($event);
            }

            return $circle;
        });
    }
}
