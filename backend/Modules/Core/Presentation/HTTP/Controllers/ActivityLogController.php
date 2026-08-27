<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Domain\ReadModels\ActivityEntry;
use Modules\Core\Domain\Repositories\ActivityLogRepositoryContract;
use Modules\Core\Presentation\HTTP\Requests\ListActivityLogsRequest;
use Modules\Core\Presentation\HTTP\Resources\ActivityLogResource;

/**
 * Reading the activity log — ADR-017 D3.
 *
 * Read-only, and there is no write endpoint by design: rows arrive from the
 * listener, and an activity log that could be posted to is not a record of
 * what happened.
 *
 * MAKES `audit.view` LIVE. The permission has existed since the catalogue was
 * written, is held by super_admin alone, and no route has ever consulted it —
 * the same dormant grant ADR-016 D17 described for contestants.update. This is
 * the moment it starts to mean something, recorded so nobody has to hunt for
 * it later.
 *
 * GOES THROUGH A REPOSITORY, NOT THE MODEL. The first version of this class
 * queried ActivityLogModel directly and the repaired architecture guard failed
 * it on the first full run — a controller reaching past its own application
 * layer into Eloquent (ADR-009/ADR-012). It was fixed rather than added to
 * CONTROLLER_MODEL_BASELINE: that list is for debt that predates the guard,
 * not a place to put new violations.
 *
 * Actor names are resolved in one batched call through CoreServiceContract
 * rather than per row: 100 rows would otherwise be 100 queries, and the DTO
 * that answers carries exactly the four fields ADR-016 D18 admits.
 */
final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogRepositoryContract $activity,
    ) {}

    public function index(ListActivityLogsRequest $request, CoreServiceContract $users): JsonResponse
    {
        $page = $this->activity->paginate($request->validated());

        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($page['items']),

            // A sibling of `data`, not a field on every row. The name is a
            // display convenience resolved live (D3 keeps roles and names out
            // of the stored row), and repeating it per row would repeat it for
            // every one of the twenty entries one person made this morning.
            'actors' => $this->resolveActors($page['items'], $users),

            'meta' => [
                'pagination' => [
                    'current_page' => $page['current_page'],
                    'per_page' => $page['per_page'],
                    'total' => $page['total'],
                    'last_page' => $page['last_page'],
                ],
            ],
        ]);
    }

    /**
     * Actor id => name, for the whole page in one query.
     *
     * Names rather than roles: ADR-017 D3 keeps `user_role` out of the log
     * because a role captured at write time stops being true when the role
     * changes. The name is resolved live here for the same reason — it is a
     * display convenience, not a stored fact.
     *
     * @param  array<int, ActivityEntry>  $rows
     * @return array<string, string>
     */
    private function resolveActors(array $rows, CoreServiceContract $users): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (ActivityEntry $row): ?string => $row->actorId, $rows)
        )));

        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach ($users->findResolvedByIds($ids) as $user) {
            $names[$user->id] = $user->name;
        }

        return $names;
    }
}
