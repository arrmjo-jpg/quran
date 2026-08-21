<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Entities;

use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Core\Domain\Concerns\HasDomainEvents;

/**
 * Contestant Aggregate Root
 *
 * Contestant profile aggregate root. Does NOT contain applications, videos, or results.
 */
final class Contestant
{
    use HasDomainEvents;

    public function __construct(
        public readonly ContestantId $id,
        public readonly string $userId,
        public readonly string $countryId,
        private string $fullName,
        private BirthDate $dateOfBirth,
        private Gender $gender,
        private string $phoneNumber,
        private ?string $nationalId = null,
        private ?string $photoMediaId = null,
        private ?string $deletedAt = null,
    ) {}

    public static function create(
        ContestantId $id,
        string $userId,
        string $countryId,
        string $fullName,
        BirthDate $dateOfBirth,
        Gender $gender,
        string $phoneNumber,
        ?string $nationalId = null,
        ?string $photoMediaId = null
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            countryId: $countryId,
            fullName: $fullName,
            dateOfBirth: $dateOfBirth,
            gender: $gender,
            phoneNumber: $phoneNumber,
            nationalId: $nationalId,
            photoMediaId: $photoMediaId
        );
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getDateOfBirth(): BirthDate
    {
        return $this->dateOfBirth;
    }

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function getPhotoMediaId(): ?string
    {
        return $this->photoMediaId;
    }

    public function updateProfile(string $fullName, string $phoneNumber, ?string $photoMediaId): void
    {
        $this->fullName = trim($fullName);
        $this->phoneNumber = trim($phoneNumber);
        $this->photoMediaId = $photoMediaId;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * The administrative edit — Epic 4 Story 1.
     *
     * Separate from updateProfile(), which is the contestant editing their
     * own record and reaches fewer fields. Collapsing the two would mean one
     * method whose reachable surface depended on who called it.
     *
     * Only arguments that are actually passed are applied, so a partial
     * update cannot blank a field by omitting it. Returns the names of the
     * fields that changed, so the use case reports a delta it did not have
     * to reconstruct.
     *
     * `userId` and `countryId` are absent by design and are readonly on this
     * class: re-pointing a contestant at a different account would silently
     * move a person's whole history, and country is frozen onto applications
     * (ADR-016 D8). Neither is an edit, and both would be a different
     * operation with its own name if they were ever wanted.
     *
     * @param  array<string, mixed>  $changes  keyed by field name
     * @return array<int, string>
     */
    public function applyAdminEdit(array $changes): array
    {
        $changed = [];

        if (array_key_exists('full_name', $changes)) {
            $value = trim((string) $changes['full_name']);
            if ($value !== $this->fullName) {
                $this->fullName = $value;
                $changed[] = 'full_name';
            }
        }

        if (array_key_exists('phone_number', $changes)) {
            $value = trim((string) $changes['phone_number']);
            if ($value !== $this->phoneNumber) {
                $this->phoneNumber = $value;
                $changed[] = 'phone_number';
            }
        }

        if (array_key_exists('date_of_birth', $changes)) {
            $value = new BirthDate((string) $changes['date_of_birth']);
            if ($value->value !== $this->dateOfBirth->value) {
                $this->dateOfBirth = $value;
                $changed[] = 'date_of_birth';
            }
        }

        if (array_key_exists('gender', $changes)) {
            $value = new Gender((string) $changes['gender']);
            if ((string) $value !== (string) $this->gender) {
                $this->gender = $value;
                $changed[] = 'gender';
            }
        }

        if (array_key_exists('national_id', $changes)) {
            $value = $changes['national_id'] === null ? null : trim((string) $changes['national_id']);
            if ($value !== $this->nationalId) {
                $this->nationalId = $value;
                $changed[] = 'national_id';
            }
        }

        if (array_key_exists('photo_media_id', $changes)) {
            $value = $changes['photo_media_id'] === null ? null : (string) $changes['photo_media_id'];
            if ($value !== $this->photoMediaId) {
                $this->photoMediaId = $value;
                $changed[] = 'photo_media_id';
            }
        }

        return $changed;
    }
}
