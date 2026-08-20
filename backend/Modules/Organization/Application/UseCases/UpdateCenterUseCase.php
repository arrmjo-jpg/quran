<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Entities\Center;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\Coordinates;

/**
 * Renames or relocates a centre.
 *
 * The country is not among the parameters, and its absence is the decision:
 * a centre that changed country would be a different centre. Its circles, and
 * every application that froze its name, belong to the place it was.
 */
final class UpdateCenterUseCase
{
    public function __construct(
        private CenterRepositoryContract $centers,
    ) {}

    public function execute(
        string $centerId,
        string $name,
        string $city,
        string $address,
        ?float $latitude = null,
        ?float $longitude = null,
    ): Center {
        return DB::transaction(function () use ($centerId, $name, $city, $address, $latitude, $longitude): Center {
            $id = new CenterId($centerId);
            $center = $this->centers->findOrFail($id);

            // Checked against the city being moved TO, not the one being left.
            // A centre relocating into a town that already has a centre of the
            // same name is exactly the collision this refuses, and checking
            // the old city would miss it.
            if ($this->centers->nameTakenInCity($center->getCountryId(), $city, $name, excluding: $id)) {
                throw new DomainException("A centre named '{$name}' already exists in {$city}.");
            }

            $center->update($name, $city, $address, Coordinates::fromNullable($latitude, $longitude));

            $this->centers->save($center);

            foreach ($center->releaseEvents() as $event) {
                event($event);
            }

            return $center;
        });
    }
}
