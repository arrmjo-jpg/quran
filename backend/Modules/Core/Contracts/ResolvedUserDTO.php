<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * ResolvedUserDTO
 *
 * Cross-module read shape for a single account. Owned by Core — consumers
 * read these four fields and cannot reach anything else about the account.
 *
 * THE FOUR FIELDS ARE ADR-016 D18, AND THE CLASS IS HOW D18 IS ENFORCED.
 * D18 measured every column on `users` and `user_profiles` against one rule —
 * *Identity 360 shows what identifies, explains, or links; it does not show
 * what describes* — and admitted exactly `id`, `name`, `status` and `type`.
 *
 * `email`, `preferred_locale`, `mfa_enabled`, `roles`, `created_at` and every
 * `user_profiles` field were each considered and each excluded. They are
 * absent here rather than merely undocumented, so a screen that wants one
 * cannot quietly add it: there is nowhere to put it, and widening this class
 * means re-opening D18 in the ADR where the reasoning lives.
 *
 * `status` is the derived value, not the raw columns — see UserStatus for why
 * it is derived in one place. A consumer receives the answer, never the three
 * facts it would have to combine correctly itself.
 */
final readonly class ResolvedUserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public string $type,
    ) {}
}
