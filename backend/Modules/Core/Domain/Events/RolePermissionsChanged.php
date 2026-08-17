<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * Carries the delta, not the resulting set — "what changed" is the
 * question an auditor asks, and reconstructing it from snapshots is
 * lossy (ADR-015 §4.5).
 */
final readonly class RolePermissionsChanged
{
    public const TYPE = 'role_permissions_changed';

    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     */
    public function __construct(
        public string $roleId,
        public string $roleName,
        public array $added,
        public array $removed,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
            'added' => $this->added,
            'removed' => $this->removed,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
