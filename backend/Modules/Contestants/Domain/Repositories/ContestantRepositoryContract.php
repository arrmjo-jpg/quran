<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Repositories;

use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\ValueObjects\ContestantId;

interface ContestantRepositoryContract
{
    public function findOrFail(ContestantId $id): Contestant;

    public function find(ContestantId $id): ?Contestant;

    /**
     * Includes soft-deleted records. Needed by restore, which by definition
     * acts on a row the ordinary finders cannot see.
     */
    public function findWithTrashed(ContestantId $id): ?Contestant;

    public function findByUserId(string $userId): ?Contestant;

    /**
     * Whether this account already has a contestant record, deleted or not.
     *
     * `contestants.user_id` is UNIQUE, so a soft-deleted contestant still
     * occupies its account — the index does not exclude deleted rows.
     * Creating a second one raises a constraint violation at the database,
     * and a 500 is the wrong way to tell an administrator that the person is
     * already registered.
     */
    public function existsForUser(string $userId): bool;

    /**
     * One page of contestants.
     *
     * Replaces the unbounded `search()` this contract carried before Epic 4
     * Story 1, which ended in `->get()` and returned every row.
     *
     * @param  array{search?: ?string, country_id?: ?string, gender?: ?string, with_deleted?: bool, per_page?: int, page?: int}  $criteria
     * @return array{items: array<int, Contestant>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(array $criteria): array;

    public function save(Contestant $contestant): void;

    /** Soft delete — ADR-016 D5. */
    public function delete(ContestantId $id): void;

    public function restore(ContestantId $id): void;
}
