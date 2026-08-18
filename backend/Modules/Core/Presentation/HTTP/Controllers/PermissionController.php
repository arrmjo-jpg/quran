<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

/**
 * The permission catalogue, read-only — ADR-015 §4.3.
 *
 * Deliberately a flat list of names and nothing else. Grouping is not sent
 * because a permission group is not a thing this system has: it is a way of
 * reading `resource.action`, and the client can derive it from the name. If
 * the server shipped groups, a new resource would need a server change to
 * become visible, and a translated group heading would have to come from
 * somewhere — a table, or a hardcoded map that drifts. Shaabjo built that
 * table and it died: `permission_group_id` was written only by the seeder,
 * so a group created from the panel could never be populated.
 *
 * There is no write side and there will not be one. Permissions are a
 * static catalogue under version control; adding one is a code change that
 * the seeder applies, so that what a role may hold is reviewable.
 */
final class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => PermissionCatalog::all(),
            ],
        ]);
    }
}
