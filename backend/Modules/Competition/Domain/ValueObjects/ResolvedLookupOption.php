<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\ValueObjects;

/**
 * ResolvedLookupOption
 *
 * A single already-fetched row from a lookup catalog (participation_types,
 * tajweed_levels, judge_score_systems, countries), carrying its code and
 * translated names — never just an id. Fetching this from the database
 * is a repository concern; this class only carries the resolved result
 * so the pure domain layer (SeasonRuleSnapshotFactory) never touches I/O.
 */
final readonly class ResolvedLookupOption
{
    /**
     * @param  array<string, string>  $name  locale => translated name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
