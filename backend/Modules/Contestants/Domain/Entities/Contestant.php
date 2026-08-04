<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Entities;

use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;

/**
 * Contestant Aggregate Root
 *
 * Contestant profile aggregate root. Does NOT contain applications, videos, or results.
 */
final class Contestant
{
    /** @var array<int, object> */
    private array $domainEvents = [];

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

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
