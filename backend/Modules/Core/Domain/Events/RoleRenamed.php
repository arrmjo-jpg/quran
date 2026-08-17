<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final readonly class RoleRenamed
{
    public const TYPE = 'role_renamed';

    public function __construct(
        public string $roleId,
        public string $oldName,
        public string $newName,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'role_id' => $this->roleId,
            'old_name' => $this->oldName,
            'new_name' => $this->newName,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
