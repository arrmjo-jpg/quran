<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Repositories;

use Modules\Organization\Domain\Entities\Circle;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Modules\Organization\Infrastructure\Database\Models\CircleModel;
use RuntimeException;

/** Eloquent is confined to this class — ADR-002. */
final class CircleRepository implements CircleRepositoryContract
{
    public function find(CircleId $id): ?Circle
    {
        $row = CircleModel::query()->find($id->value);

        return $row === null ? null : $this->toEntity($row);
    }

    public function findOrFail(CircleId $id): Circle
    {
        $circle = $this->find($id);

        if ($circle === null) {
            throw new RuntimeException("Circle {$id->value} not found.");
        }

        return $circle;
    }

    public function nameTakenInCenter(string $centerId, string $name, ?CircleId $excluding = null): bool
    {
        return CircleModel::query()
            ->where('center_id', $centerId)
            ->where('name', trim($name))
            ->when($excluding !== null, fn ($q) => $q->where('id', '!=', $excluding->value))
            ->exists();
    }

    public function countInCenter(CenterId $centerId): int
    {
        // SoftDeletes scopes this to live rows, which is the intent: a closed
        // circle does not keep its centre open.
        return CircleModel::query()->where('center_id', $centerId->value)->count();
    }

    public function save(Circle $circle): void
    {
        CircleModel::query()->updateOrCreate(
            ['id' => $circle->id->value],
            [
                'name' => $circle->getName(),
                'center_id' => $circle->getCenterId(),
                'supervisor_user_id' => $circle->getSupervisorUserId(),
            ]
        );
    }

    public function delete(CircleId $id): void
    {
        CircleModel::query()->where('id', $id->value)->delete();
    }

    private function toEntity(CircleModel $row): Circle
    {
        return Circle::reconstitute(
            id: new CircleId((string) $row->id),
            name: (string) $row->name,
            centerId: (string) $row->center_id,
            supervisorUserId: $row->supervisor_user_id === null ? null : (string) $row->supervisor_user_id,
        );
    }
}
