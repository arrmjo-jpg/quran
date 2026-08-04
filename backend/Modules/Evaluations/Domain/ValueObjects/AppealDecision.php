<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * AppealDecision Value Object
 *
 * Audit-ready decision record for contestant appeals.
 */
final readonly class AppealDecision
{
    public function __construct(
        public string $reviewerId,
        public string $decision, // 'accepted', 'rejected'
        public string $reason,
        public string $decidedAtIso,
    ) {
        if (! in_array($decision, ['accepted', 'rejected'], true)) {
            throw new InvalidArgumentException("Invalid appeal decision: {$decision}");
        }
    }
}
