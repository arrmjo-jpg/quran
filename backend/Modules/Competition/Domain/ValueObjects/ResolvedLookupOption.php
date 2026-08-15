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
     * @param  int|null  $displayOrder  catalog sort position; only populated on the
     *                                  admin picker path, null everywhere else
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?int $displayOrder = null,
    ) {}

    /**
     * Deliberately does NOT include displayOrder. This feeds
     * SeasonRuleSnapshotFactory, and a frozen season's snapshot is a
     * versioned record of the rules entrants signed up under — a catalog's
     * presentation order is not part of that and must not drift into it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
