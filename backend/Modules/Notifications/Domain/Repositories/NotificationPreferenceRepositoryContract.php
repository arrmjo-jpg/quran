<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Repositories;

/**
 * Where an account's declined notifications are stored — ADR-020 D4.
 *
 * NO ENTITY BEHIND THIS, DELIBERATELY. A preference is one boolean for one
 * (account, type) pair and holds no invariant of its own: the rule that
 * matters — that some types may never be declined — belongs to the catalogue
 * (D9) and is enforced before anything reaches storage. An aggregate here
 * would be a class with a constructor and a getter, which is ceremony rather
 * than a boundary.
 *
 * IT DEALS IN DECLINES, NOT IN SETTINGS. Absence means enabled, so the
 * question the send path asks is "has this been turned off", and the answer
 * for an account that never opened the screen is a table with no rows in it.
 */
interface NotificationPreferenceRepositoryContract
{
    /**
     * The types this account has turned off.
     *
     * @return array<int, string>
     */
    public function declinedTypesFor(string $userId): array;

    /**
     * Record a decision. `true` restores the default rather than storing a
     * second kind of row, so the table only ever grows by explicit refusals.
     */
    public function set(string $userId, string $type, bool $enabled): void;
}
