<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * Carries the deleted role's name and the permissions it granted, because
 * the row is gone by the time a listener sees this — an audit entry that
 * only recorded an id would be unreadable a week later.
 */
final readonly class RoleDeleted
{
    public const TYPE = 'role_deleted';

    /** @param array<int, string> $permissions */
    public function __construct(
        public string $roleId,
        public string $name,
        public array $permissions,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'role_id' => $this->roleId,
            'name' => $this->name,
            'permissions' => $this->permissions,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
