<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;
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
 * Actor names are resolved in one batched call through CoreServiceContract
 * rather than per row: 100 rows would otherwise be 100 queries, and the DTO
 * that answers carries exactly the four fields ADR-016 D18 admits.
 */
final class ActivityLogController extends Controller
{
    /** How many rows a page holds unless the caller says otherwise. */
    private const DEFAULT_PER_PAGE = 25;

    public function index(ListActivityLogsRequest $request, CoreServiceContract $users): JsonResponse
    {
        $query = ActivityLogModel::query();

        if ($entityType = $request->validated('entity_type')) {
            $query->where('entity_type', $entityType)
                ->where('entity_id', $request->validated('entity_id'));
        }

        if ($actorId = $request->validated('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        if ($action = $request->validated('action')) {
            $query->where('action', $action);
        }

        if ($correlationId = $request->validated('correlation_id')) {
            $query->where('correlation_id', $correlationId);
        }

        // Windowed on occurred_at, not created_at — a backdated entry belongs
        // to the period it describes.
        if ($from = $request->validated('from')) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to = $request->validated('to')) {
            $query->where('occurred_at', '<=', $to);
        }

        $paginator = $query
            ->orderByDesc('occurred_at')
            // Tie-broken by id so a page boundary cannot drop or repeat a row
            // when several events share a timestamp — which they do, because
            // one request can dispatch a handful in the same second.
            ->orderByDesc('id')
            ->paginate(
                perPage: (int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE),
                page: (int) ($request->validated('page') ?? 1),
            );

        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($paginator->items()),

            // A sibling of `data`, not a field on every row. The name is a
            // display convenience resolved live (D3 keeps roles and names out
            // of the stored row), and repeating it per row would repeat it for
            // every one of the twenty entries one person made this morning.
            //
            // Set here rather than through Resource::additional(), which only
            // reaches the response when the collection IS the response — it is
            // silently dropped when the collection is nested inside a hand-
            // built array like this one.
            'actors' => $this->resolveActors($paginator->items(), $users),

            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
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
     * @param  array<int, ActivityLogModel>  $rows
     * @return array<string, string>
     */
    private function resolveActors(array $rows, CoreServiceContract $users): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (ActivityLogModel $row): ?string => $row->actor_id, $rows)
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
