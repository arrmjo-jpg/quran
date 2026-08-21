<?php

declare(strict_types=1);

namespace Modules\Contestants\Infrastructure\Database\Repositories;

use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;

final class ContestantRepository implements ContestantRepositoryContract
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function findOrFail(ContestantId $id): Contestant
    {
        return $this->toDomain(ContestantModel::query()->findOrFail($id->value));
    }

    public function find(ContestantId $id): ?Contestant
    {
        $model = ContestantModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findWithTrashed(ContestantId $id): ?Contestant
    {
        $model = ContestantModel::withTrashed()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByUserId(string $userId): ?Contestant
    {
        $model = ContestantModel::query()->where('user_id', $userId)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function existsForUser(string $userId): bool
    {
        // withTrashed on purpose: the UNIQUE index on user_id does not
        // exclude soft-deleted rows, so a deleted contestant still holds the
        // account and a second insert would hit the constraint.
        return ContestantModel::withTrashed()->where('user_id', $userId)->exists();
    }

    /**
     * @param  array{search?: ?string, country_id?: ?string, gender?: ?string, with_deleted?: bool, per_page?: int, page?: int}  $criteria
     * @return array{items: array<int, Contestant>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(array $criteria): array
    {
        $query = ($criteria['with_deleted'] ?? false)
            ? ContestantModel::withTrashed()
            : ContestantModel::query();

        if (! empty($criteria['country_id'])) {
            $query->where('country_id', $criteria['country_id']);
        }

        if (! empty($criteria['gender'])) {
            $query->where('gender', $criteria['gender']);
        }

        $search = $criteria['search'] ?? null;

        if ($search !== null && $search !== '') {
            // full_name and phone_number only. national_id was searchable
            // before Epic 4 Story 1 and is not any more: a leading-wildcard
            // LIKE over an identity document lets a holder of
            // contestants.view confirm or enumerate fragments of it, and the
            // subjects may be minors — the same reasoning D11 uses to
            // withhold contestant visibility from supervisors until scoping
            // is real. Nothing in the admin UI searched by it.
            $query->where(function ($inner) use ($search): void {
                $inner->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) ($criteria['per_page'] ?? self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);

        $paginator = $query->orderBy('full_name')->paginate(
            perPage: $perPage,
            page: max((int) ($criteria['page'] ?? 1), 1),
        );

        return [
            'items' => array_map(
                fn (ContestantModel $model): Contestant => $this->toDomain($model),
                $paginator->items()
            ),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function save(Contestant $contestant): void
    {
        ContestantModel::query()->updateOrCreate(
            ['id' => (string) $contestant->id],
            [
                'user_id' => $contestant->userId,
                'country_id' => $contestant->countryId,
                'full_name' => $contestant->getFullName(),
                'date_of_birth' => (string) $contestant->getDateOfBirth(),
                'gender' => (string) $contestant->getGender(),
                'phone_number' => $contestant->getPhoneNumber(),
                'national_id' => $contestant->getNationalId(),
                'photo_media_id' => $contestant->getPhotoMediaId(),
            ]
        );
    }

    public function delete(ContestantId $id): void
    {
        // Eloquent's delete() on a SoftDeletes model sets deleted_at. That it
        // is soft is the whole point (ADR-016 D5): applications and
        // memberships reference this row with RESTRICT, and a hard delete
        // would either fail or take history with it.
        ContestantModel::query()->where('id', $id->value)->delete();
    }

    public function restore(ContestantId $id): void
    {
        ContestantModel::withTrashed()->where('id', $id->value)->restore();
    }

    private function toDomain(ContestantModel $model): Contestant
    {
        return new Contestant(
            id: new ContestantId($model->id),
            userId: $model->user_id,
            countryId: $model->country_id,
            fullName: $model->full_name,
            dateOfBirth: new BirthDate($model->date_of_birth->format('Y-m-d')),
            gender: new Gender($model->gender),
            phoneNumber: $model->phone_number,
            nationalId: $model->national_id,
            photoMediaId: $model->photo_media_id,
            deletedAt: $model->deleted_at?->toIso8601String()
        );
    }
}
