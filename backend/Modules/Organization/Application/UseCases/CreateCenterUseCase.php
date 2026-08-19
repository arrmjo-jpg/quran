<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\Center;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\Coordinates;

final class CreateCenterUseCase
{
    public function __construct(
        private CenterRepositoryContract $centers,
    ) {}

    public function execute(
        string $name,
        string $countryId,
        string $city,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
    ): Center {
        return DB::transaction(function () use ($name, $countryId, $city, $address, $latitude, $longitude): Center {
            // Checked here as well as by the unique index. The index is the
            // guarantee; this is the readable refusal, and without it a
            // duplicate would surface as a driver exception nobody can act on.
            if ($this->centers->nameTakenInCity($countryId, $city, $name)) {
                throw new DomainException("A centre named '{$name}' already exists in {$city}.");
            }

            $center = Center::create(
                id: CenterId::generate(),
                name: $name,
                countryId: $countryId,
                city: $city,
                address: $address,
                coordinates: Coordinates::fromNullable($latitude, $longitude),
            );

            $this->centers->save($center);

            foreach ($center->releaseEvents() as $event) {
                event($event);
            }

            return $center;
        });
    }
}
