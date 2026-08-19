<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\RoleId;
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

    /** @var array<string, RoleId> keyed by id, so assignment is idempotent */
    private array $roleIds = [];

    /**
     * @param  array<int, RoleId>  $roleIds  the roles this user holds. Ids
     *                                       only: resolving them to
     *                                       permissions is a separate
     *                                       concern and a later epic.
     */
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
        array $roleIds = [],
    ) {
        foreach ($roleIds as $roleId) {
            $this->roleIds[$roleId->value] = $roleId;
        }
    }

    /**
     * An account created by an administrator and not yet claimed — ADR-016 D14.
     *
     * A second named constructor rather than nullable parameters on create(),
     * because the two births are genuinely different and naming them keeps
     * either from drifting. create() is public registration: the person is
     * present, chooses a password, and the account is live immediately.
     * invite() is provisioning: nobody has chosen a password yet, so there is
     * none, and the account cannot be used until its invitee claims it.
     *
     * password_hash IS NULL is what makes this account "pending" rather than
     * "deactivated" — both are is_active = false, and the difference is
     * whether a password was ever set. Derived rather than stored, so it
     * cannot disagree with the column it describes.
     */
    public static function invite(
        UserId $id,
        Email $email,
        string $name,
        UserType $type,
        Locale $preferredLocale = new Locale('ar')
    ): self {
        return new self(
            id: $id,
            email: $email,
            name: $name,
            type: $type,
            passwordHash: null,
            preferredLocale: $preferredLocale,
            isActive: false,
        );
    }

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

    /*
    |--------------------------------------------------------------------
    | Roles held — ADR-015 §3
    |--------------------------------------------------------------------
    | The aggregate holds role IDS and nothing more. It cannot answer
    | "what may this user do": that needs every role's permission set
    | resolved, which is a read model and a later epic. Keeping the
    | question unanswerable here is deliberate — an aggregate that could
    | half-answer it would invite callers to depend on the half.
    |
    | assignRole/revokeRole/syncRoles record no events. The delta needs
    | role NAMES to be worth reading in an audit trail, and names live on
    | Role, which this aggregate does not load. The use case resolves them
    | and emits UserRolesChanged.
    */

    /** @return array<int, RoleId> */
    public function getRoleIds(): array
    {
        return array_values($this->roleIds);
    }

    /** @return array<int, string> */
    public function getRoleIdValues(): array
    {
        return array_keys($this->roleIds);
    }

    public function hasRole(RoleId $roleId): bool
    {
        return isset($this->roleIds[$roleId->value]);
    }

    public function assignRole(RoleId $roleId): void
    {
        $this->roleIds[$roleId->value] = $roleId;
    }

    public function revokeRole(RoleId $roleId): void
    {
        unset($this->roleIds[$roleId->value]);
    }

    /**
     * Replace the whole set. Callers pass the intended final state for
     * the same reason Role::syncPermissions() does: separate add/remove
     * calls from two clients can interleave into a set neither intended.
     *
     * @param  array<int, RoleId>  $roleIds
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roleIds = [];

        foreach ($roleIds as $roleId) {
            $this->roleIds[$roleId->value] = $roleId;
        }
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
