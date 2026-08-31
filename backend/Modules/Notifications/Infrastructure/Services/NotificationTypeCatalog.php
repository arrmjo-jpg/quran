<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Services;

use Modules\Notifications\Contracts\NotificationTypeCatalogContract;

/**
 * The catalogue itself — ADR-020 D4, D9.
 *
 * A singleton for the same reason NotificationMailRegistry is one:
 * registrations happen once during boot, and an instance created per
 * resolution would be empty by the time anything asked it a question. An empty
 * catalogue answers "unknown" to every type, which would make the invitation
 * declinable — the one outcome D9 exists to prevent.
 */
final class NotificationTypeCatalog implements NotificationTypeCatalogContract
{
    /** @var array<string, bool> type => mandatory */
    private array $types = [];

    public function register(string $type, bool $mandatory = false): void
    {
        $this->types[$type] = $mandatory;
    }

    public function isKnown(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }

    public function isMandatory(string $type): bool
    {
        return $this->types[$type] ?? false;
    }

    public function declinable(): array
    {
        return array_values(array_keys(array_filter(
            $this->types,
            static fn (bool $mandatory): bool => ! $mandatory
        )));
    }
}
