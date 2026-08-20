<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\Exceptions;

use DomainException;

/**
 * This contestant already has an application for this season and stage.
 *
 * The uniqueness is on the triple, not on the contestant: applying to a later
 * stage of the same season, or to a different season entirely, is ordinary.
 */
final class DuplicateApplicationException extends DomainException
{
    public static function for(string $contestantId, string $seasonId, string $stageId): self
    {
        return new self(
            "Contestant {$contestantId} already has an application for season {$seasonId}, stage {$stageId}."
        );
    }
}
