<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;

/**
 * User Aggregate Root
 *
 * Framework-free identity aggregate root governing user credentials,
 * surface discriminator, and active status.
 *
 * Per Architecture Mandates:
 * - NO Contestant, Judge, or Season fields exist here. Profiles are separate.
 * - Enforces state transitions via explicit domain methods.
 */
final class User
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        public readonly UserId $id,
        private Email $email,
        private string $name,
        private UserType $type,
        private ?PasswordHash $passwordHash = null,
        private Locale $preferredLocale = new Locale('ar'),
        private bool $isActive = true,
        private ?string $emailVerifiedAt = null,
        private ?string $lastLoginAt = null,
        private ?string $deletedAt = null,
    ) {}

    public static function create(
        UserId $id,
        Email $email,
        string $name,
        UserType $type,
        PasswordHash $passwordHash,
        Locale $preferredLocale = new Locale('ar')
    ): self {
        return new self(
            id: $id,
            email: $email,
            name: $name,
            type: $type,
            passwordHash: $passwordHash,
            preferredLocale: $preferredLocale,
            isActive: true,
        );
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function isAdmin(): bool
    {
        return $this->type->isAdmin();
    }

    public function getPasswordHash(): ?PasswordHash
    {
        return $this->passwordHash;
    }

    public function getPreferredLocale(): Locale
    {
        return $this->preferredLocale;
    }

    public function isActive(): bool
    {
        return $this->isActive && $this->deletedAt === null;
    }

    public function updateProfile(string $name, Locale $locale): void
    {
        $this->name = trim($name);
        $this->preferredLocale = $locale;
    }

    public function changePassword(PasswordHash $newHash): void
    {
        $this->passwordHash = $newHash;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function recordLogin(string $timestampIso): void
    {
        $this->lastLoginAt = $timestampIso;
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
