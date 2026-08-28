<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ReadModels;

/**
 * One login attempt — ADR-018 D2.
 *
 * A read model over `audit_logs`, not a table of its own. The rows already
 * exist; this gives them a shape the presentation layer can hold without the
 * controller reaching into Eloquent.
 *
 * `outcome` is derived here rather than stored. ADR-017 D3 refused a `result`
 * column on `activity_logs` because a domain event that has been dispatched has
 * already happened — there is no unsuccessful one. HTTP is the opposite: a
 * request can fail, `audit_logs.response_status` records that it did, and a
 * login history whose entire subject is "did this work?" would be useless
 * without it.
 */
final readonly class LoginAttempt
{
    public function __construct(
        public string $id,

        /** Null when the email matched no account — there is nobody to name. */
        public ?string $userId,
        public ?string $userType,

        /** 'success' | 'invalid_credentials' | 'account_inactive' | 'rate_limited' | 'failed' */
        public string $outcome,
        public int $status,

        public ?string $ip,
        public ?string $userAgent,

        /** From X-Device-ID (ADR-018 D7). Null for every row written before it was sent. */
        public ?string $deviceId,

        /** Joins this attempt to everything else that request touched. */
        public ?string $correlationId,

        public ?string $attemptedAt,
    ) {}
}
