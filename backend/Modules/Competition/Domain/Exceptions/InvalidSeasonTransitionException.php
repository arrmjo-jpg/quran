<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

final class InvalidSeasonTransitionException extends DomainException
{
    /**
     * @param  array<int, string>  $allowed
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $allowed,
    ) {
        $allowedList = $allowed === [] ? '(none — terminal state)' : implode(', ', $allowed);
        parent::__construct("Cannot transition season from '{$from}' to '{$to}'. Allowed: {$allowedList}");
    }
}
