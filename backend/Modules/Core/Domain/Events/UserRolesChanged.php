<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * Carries the delta and the role NAMES rather than only ids — an audit
 * entry reading "granted 0192...-7f3a" is unreadable, and the role may
 * have been renamed or deleted by the time anyone looks (ADR-015 §4.5).
 */
final readonly class UserRolesChanged
{
    public const TYPE = 'user_roles_changed';

    /**
     * @param  array<int, string>  $added  role names
     * @param  array<int, string>  $removed  role names
     */
    public function __construct(
        public string $userId,
        public array $added,
        public array $removed,
        public ?string $byUserId,
        public string $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'added' => $this->added,
            'removed' => $this->removed,
            'by_user_id' => $this->byUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
