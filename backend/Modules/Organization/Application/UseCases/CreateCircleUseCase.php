<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\Circle;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\CircleId;

final class CreateCircleUseCase
{
    public function __construct(
        private CircleRepositoryContract $circles,
        private CenterRepositoryContract $centers,
    ) {}

    public function execute(
        string $name,
        string $centerId,
        ?string $supervisorUserId = null,
    ): Circle {
        return DB::transaction(function () use ($name, $centerId, $supervisorUserId): Circle {
            // Resolved rather than assumed. The request rule proves a row with
            // this id exists; this proves it is still there inside the
            // transaction, and gives a domain-shaped failure if it is not.
            $this->centers->findOrFail(new CenterId($centerId));

            // Checked here as well as by the unique index. The index is the
            // guarantee; this is the readable refusal, and without it a
            // duplicate would surface as a driver exception nobody can act on.
            if ($this->circles->nameTakenInCenter($centerId, $name)) {
                throw new DomainException("A circle named '{$name}' already exists at this centre.");
            }

            $circle = Circle::create(
                id: CircleId::generate(),
                name: $name,
                centerId: $centerId,
                supervisorUserId: $supervisorUserId,
            );

            $this->circles->save($circle);

            foreach ($circle->releaseEvents() as $event) {
                event($event);
            }

            return $circle;
        });
    }
}
