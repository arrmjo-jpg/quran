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
    public function findOrFail(ContestantId $id): Contestant
    {
        $model = ContestantModel::query()->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function find(ContestantId $id): ?Contestant
    {
        $model = ContestantModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByUserId(string $userId): ?Contestant
    {
        $model = ContestantModel::query()->where('user_id', $userId)->first();

        return $model ? $this->toDomain($model) : null;
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
                'photo_media_id' => $contestant->getPhotoMediaId(),
            ]
        );
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
