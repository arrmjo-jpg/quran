<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * UserStatus Value Object
 *
 * The one derived answer to "can this person sign in, and if not, why not?"
 *
 * The account has no status column. What it has is three facts —
 * `deleted_at`, `password_hash` and `is_active` — and a status is the single
 * reading of them. `pending_activation` and `deactivated` are BOTH
 * `is_active = false`, so a caller that combined the three itself and got the
 * combination wrong would report a colleague invited an hour ago as disabled.
 *
 * PRECEDENCE IS PART OF THE DEFINITION, not an implementation detail of
 * whoever wrote the match first. Deleted outranks everything, because it is
 * the most consequential thing true about an account and a deleted-but-never
 * -activated row is deleted first. An unset password outranks inactivity,
 * because an invitation that has not been accepted explains the inactivity.
 *
 * WHY THIS IS A CLASS AND NOT A MATCH IN A RESOURCE. It was a match in
 * AdminUserResource, and that was right while exactly one screen asked the
 * question. Epic 4's Identity 360 asks it again from a different module, and
 * two copies of a four-branch precedence rule are two copies that can drift —
 * at which point the contestant screen and the users screen disagree about
 * whether somebody can sign in, which is precisely the confusion the derived
 * status was introduced to end.
 */
final readonly class UserStatus
{
    public const ACTIVE = 'active';

    public const PENDING_ACTIVATION = 'pending_activation';

    public const DEACTIVATED = 'deactivated';

    public const DELETED = 'deleted';

    public const ALLOWED = [self::ACTIVE, self::PENDING_ACTIVATION, self::DEACTIVATED, self::DELETED];

    public function __construct(
        public string $value,
    ) {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                "Unsupported user status: {$value}. Allowed: ".implode(', ', self::ALLOWED)
            );
        }
    }

    /**
     * Reads the three columns the way every screen must read them.
     *
     * Takes the facts rather than a model, so the Domain layer stays free of
     * Eloquent and a caller holding a row, an entity or three variables can
     * all ask the same question.
     */
    public static function derive(bool $isDeleted, bool $hasPassword, bool $isActive): self
    {
        return new self(match (true) {
            $isDeleted => self::DELETED,
            ! $hasPassword => self::PENDING_ACTIVATION,
            ! $isActive => self::DEACTIVATED,
            default => self::ACTIVE,
        });
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
