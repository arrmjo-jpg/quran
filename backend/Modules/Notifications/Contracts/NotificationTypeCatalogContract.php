<?php

declare(strict_types=1);

namespace Modules\Notifications\Contracts;

/**
 * Which notifications exist, and which of them may be declined — ADR-020 D4, D9.
 *
 * D9's exact words are that the mandatory set is "expressed in the code rather
 * than left to the data", and this is where. A preference row can be edited,
 * inserted by a migration, or written by a future admin screen; if
 * `invitation.created` were mandatory only because no row said otherwise, one
 * row would be enough to lock an account out of itself permanently — the
 * invitation is the only way an account can be claimed (ADR-016 D14).
 *
 * REGISTERED BY THE OWNING MODULE, like NotificationMailRegistryContract and
 * for the same reason: Notifications must not import Core to know that Core
 * sends invitations (ADR-002). Core declares its own type at boot.
 *
 * WHAT IT IS NOT: a template registry. Nothing here renders or stores a
 * message. It answers two questions — is this type known, and may it be
 * declined — and that is all the preference system needs.
 *
 * TODAY IT HOLDS EXACTLY ONE TYPE, and that one is mandatory, so the declinable
 * list is empty. That is a measurement rather than an omission: the invitation
 * is the only mail the platform sends. The list fills itself as modules start
 * notifying, and the enforcement below is already in the send path waiting.
 */
interface NotificationTypeCatalogContract
{
    /**
     * Declare a notification type this platform can send.
     *
     * @param  bool  $mandatory  True if the account may never decline it. Reserved
     *                           for notifications without which the account cannot
     *                           function — today only the invitation (D9).
     */
    public function register(string $type, bool $mandatory = false): void;

    public function isKnown(string $type): bool;

    /**
     * True when the type may never be declined. Unknown types are NOT
     * mandatory: an unregistered type is a gap in a module's own declaration,
     * and answering "mandatory" would silently force delivery of something
     * nobody declared.
     */
    public function isMandatory(string $type): bool;

    /**
     * The types an account may actually turn off.
     *
     * This is what a preferences screen lists. Mandatory types are absent
     * rather than shown-and-disabled: a control that cannot be operated is a
     * question the reader has to answer twice.
     *
     * @return array<int, string>
     */
    public function declinable(): array;
}
