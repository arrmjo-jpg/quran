<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final readonly class RoleCreated
{
    public const TYPE = 'role_created';

    public function __construct(
        public string $roleId,
        public string $name,
        public bool $isSystem,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'role_id' => $this->roleId,
            'name' => $this->name,
            'is_system' => $this->isSystem,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
