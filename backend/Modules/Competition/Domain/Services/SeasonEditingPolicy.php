<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Services;

use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;

/**
 * SeasonEditingPolicy
 *
 * Owns one question: may things that belong to a season — its stages,
 * its stage rules — still be changed?
 *
 * A Stage is its own aggregate and cannot see its season's state, so
 * something has to answer that on its behalf. Season briefly answered it
 * directly, via a public assertMutable(), which put an assertion method on
 * the aggregate's public surface and invited callers to depend on the
 * guard rather than on Season's behaviour. This service is that answer's
 * proper owner instead: Season keeps its own setters guarded privately and
 * exposes only the isFrozen() query, and every use case that edits
 * something season-owned asks here.
 *
 * Pure domain — no repositories, no I/O. It is handed an already-loaded
 * Season and decides.
 */
final readonly class SeasonEditingPolicy
{
    /**
     * @param  string  $field  what is being changed, for the error message ('stages', 'stage_rules', ...)
     */
    public function assertEditable(Season $season, string $field): void
    {
        if ($season->isFrozen()) {
            throw new SeasonAlreadyFrozenException($season->id, $field);
        }
    }
}
