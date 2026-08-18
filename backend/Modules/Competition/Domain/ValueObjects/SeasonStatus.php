<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * SeasonStatus
 *
 * The seven lifecycle states a Season may occupy, per the approved
 * Season Architecture v2 design. This value object only validates that
 * a string is one of the known states — the *legality of a transition*
 * between two states is SeasonStateMachine's job, not this class's.
 */
final readonly class SeasonStatus
{
    public const DRAFT = 'draft';

    public const REGISTRATION_OPEN = 'registration_open';

    public const REGISTRATION_CLOSED = 'registration_closed';

    public const COMPETITION_RUNNING = 'competition_running';

    public const JUDGING = 'judging';

    public const COMPLETED = 'completed';

    public const ARCHIVED = 'archived';

    private const ALL = [
        self::DRAFT,
        self::REGISTRATION_OPEN,
        self::REGISTRATION_CLOSED,
        self::COMPETITION_RUNNING,
        self::JUDGING,
        self::COMPLETED,
        self::ARCHIVED,
    ];

    public function __construct(
        public string $value,
    ) {
        if (! in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException("Invalid season status: {$value}");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function is(string $status): bool
    {
        return $this->value === $status;
    }

    public function isTerminal(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
