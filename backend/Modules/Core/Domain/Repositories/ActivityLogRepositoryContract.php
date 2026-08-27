<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\ReadModels\ActivityEntry;

interface ActivityLogRepositoryContract
{
    /**
     * A page of the activity feed, newest by when things HAPPENED.
     *
     * Read-only, and there is no save(): rows are written by the listener
     * through the model, and a repository that could write one would invite a
     * caller to forge history.
     *
     * Criteria are the filters ListActivityLogsRequest validates —
     * entity_type + entity_id, actor_id, action, correlation_id, from, to,
     * per_page, page. Absent keys mean "no filter" rather than "match null".
     *
     * @param  array<string, mixed>  $criteria
     * @return array{items: array<int, ActivityEntry>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(array $criteria): array;
}
