<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class NotificationChannel
{
    private const ALLOWED = ['email', 'sms', 'push'];

    public function __construct(
        public string $value,
    ) {
        $clean = strtolower(trim($value));
        if (! in_array($clean, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Invalid notification channel: {$value}. Allowed: email, sms, push");
        }
    }

    public function __toString(): string
    {
        return strtolower($this->value);
    }
}
