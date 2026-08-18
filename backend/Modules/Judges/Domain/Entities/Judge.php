<?php

declare(strict_types=1);

namespace Modules\Judges\Domain\Entities;

use Modules\Core\Domain\Concerns\HasDomainEvents;

/**
 * Judge Aggregate Root
 *
 * Judge profile aggregate root. Does NOT contain evaluation scores.
 */
final class Judge
{
    use HasDomainEvents;

    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        private string $fullName,
        private string $specialization, // 'tajweed', 'hifz', 'sawt', 'general'
        private ?string $title = null,
        private ?string $bio = null,
        private ?string $photoMediaId = null,
        private bool $isActive = true,
        private ?string $deletedAt = null,
    ) {}

    public static function create(
        string $id,
        string $userId,
        string $fullName,
        string $specialization,
        ?string $title = null,
        ?string $bio = null,
        ?string $photoMediaId = null
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            fullName: $fullName,
            specialization: $specialization,
            title: $title,
            bio: $bio,
            photoMediaId: $photoMediaId,
            isActive: true
        );
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getSpecialization(): string
    {
        return $this->specialization;
    }

    public function isActive(): bool
    {
        return $this->isActive && $this->deletedAt === null;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }
}
