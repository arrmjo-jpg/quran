<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Exceptions;

use DomainException;

/**
 * Thrown when a stage that something else already points at — a season
 * stage rule, a judge assignment, a stream, an application, or a stage
 * result — is put up for deletion. Deletion is permitted only while a
 * stage is still unused; once referenced it is forbidden permanently.
 * There is deliberately no soft delete or archive fallback: the record
 * stays exactly as it is.
 */
final class StageInUseException extends DomainException
{
    public function __construct(
        public readonly string $stageId,
    ) {
        parent::__construct("Stage {$stageId} is referenced by existing competition data and can no longer be deleted.");
    }
}
