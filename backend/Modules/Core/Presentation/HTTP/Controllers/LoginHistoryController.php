<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Domain\ReadModels\LoginAttempt;
use Modules\Core\Domain\Repositories\LoginHistoryRepositoryContract;
use Modules\Core\Presentation\HTTP\Requests\ListLoginHistoryRequest;
use Modules\Core\Presentation\HTTP\Resources\LoginAttemptResource;

/**
 * Reading the login history — ADR-018 D2, D3.
 *
 * Read-only. The rows are written by AuditLoggingMiddleware as a side effect of
 * the request being served; there is no endpoint that could add one.
 *
 * MAKES `security.view` LIVE, and it is a NEW permission rather than a reuse of
 * `audit.view` (D3). Epic 5 gave that one a specific meaning three commits
 * earlier — the activity feed — and folding this into it would mean a grant
 * issued for one screen silently conferring the other.
 *
 * GOES THROUGH A REPOSITORY, NOT THE MODEL, for the reason recorded on
 * ActivityLogController: a controller reaching into Eloquent is what the
 * architecture guard exists to fail, and the fix is a read model rather than a
 * baseline entry.
 */
final class LoginHistoryController extends Controller
{
    public function __construct(
        private readonly LoginHistoryRepositoryContract $history,
    ) {}

    public function index(ListLoginHistoryRequest $request, CoreServiceContract $users): JsonResponse
    {
        $page = $this->history->paginate($request->validated());

        return response()->json([
            'success' => true,
            'data' => LoginAttemptResource::collection($page['items']),

            // A sibling of `data`, not a field on every row -- the same shape
            // the activity feed uses, and for the same reason: one person's
            // forty attempts this morning should not carry forty copies of
            // their name. Resolved live, so it cannot go stale.
            'users' => $this->resolveUsers($page['items'], $users),

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
     * User id => name, for the whole page in one query.
     *
     * @param  array<int, LoginAttempt>  $rows
     * @return array<string, string>
     */
    private function resolveUsers(array $rows, CoreServiceContract $users): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (LoginAttempt $row): ?string => $row->userId, $rows)
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
