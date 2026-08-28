<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\ReadModels\LoginAttempt;

interface LoginHistoryRepositoryContract
{
    /**
     * A page of login attempts, newest first — ADR-018 D2.
     *
     * Read-only, and there is no save(): the rows are written by
     * AuditLoggingMiddleware as a side effect of the request, and a repository
     * that could write one would invite a caller to forge a login that never
     * happened.
     *
     * Criteria are the filters ListLoginHistoryRequest validates — user_id,
     * outcome, ip, from, to, per_page, page. Absent keys mean "no filter"
     * rather than "match null".
     *
     * @param  array<string, mixed>  $criteria
     * @return array{items: array<int, LoginAttempt>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(array $criteria): array;
}
