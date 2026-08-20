<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Repositories;

use Modules\Organization\Domain\Entities\Center;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\Coordinates;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;
use RuntimeException;

/** Eloquent is confined to this class — ADR-002. */
final class CenterRepository implements CenterRepositoryContract
{
    public function find(CenterId $id): ?Center
    {
        $row = CenterModel::query()->find($id->value);

        return $row === null ? null : $this->toEntity($row);
    }

    public function findOrFail(CenterId $id): Center
    {
        $center = $this->find($id);

        if ($center === null) {
            throw new RuntimeException("Center {$id->value} not found.");
        }

        return $center;
    }

    public function nameTakenInCity(string $countryId, string $city, string $name, ?CenterId $excluding = null): bool
    {
        return CenterModel::query()
            ->where('country_id', $countryId)
            ->where('city', trim($city))
            ->where('name', trim($name))
            ->when($excluding !== null, fn ($q) => $q->where('id', '!=', $excluding->value))
            ->exists();
    }

    public function save(Center $center): void
    {
        $coordinates = $center->getCoordinates();

        CenterModel::query()->updateOrCreate(
            ['id' => $center->id->value],
            [
                'name' => $center->getName(),
                'country_id' => $center->getCountryId(),
                'city' => $center->getCity(),
                'address' => $center->getAddress(),
                'latitude' => $coordinates?->latitude,
                'longitude' => $coordinates?->longitude,
            ]
        );
    }

    public function delete(CenterId $id): void
    {
        CenterModel::query()->where('id', $id->value)->delete();
    }

    private function toEntity(CenterModel $row): Center
    {
        return Center::reconstitute(
            id: new CenterId((string) $row->id),
            name: (string) $row->name,
            countryId: (string) $row->country_id,
            city: (string) $row->city,
            address: (string) $row->address,
            coordinates: Coordinates::fromNullable($row->latitude, $row->longitude),
        );
    }
}
