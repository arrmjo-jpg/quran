<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Application\UseCases\ActivateUserUseCase;
use Modules\Core\Application\UseCases\DeactivateUserUseCase;
use Modules\Core\Application\UseCases\SyncUserRolesUseCase;
use Modules\Core\Domain\Exceptions\LastSystemRoleHolderException;
use Modules\Core\Domain\Exceptions\PrivilegeEscalationException;
use Modules\Core\Domain\Exceptions\SelfDeactivationException;
use Modules\Core\Domain\Exceptions\SelfRoleChangeException;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Permissions\AuthorizationService;
use Modules\Core\Presentation\HTTP\Requests\ListUsersRequest;
use Modules\Core\Presentation\HTTP\Requests\SyncUserRolesRequest;
use Modules\Core\Presentation\HTTP\Resources\AdminUserResource;

/**
 * Account administration — ADR-015 §5.
 *
 * There is no create endpoint, deliberately. ADR-003 provisions administrators
 * by hand, and contestants arrive through public registration; an admin-facing
 * "add user" would be a third way in that neither document describes.
 *
 * Every write goes through a use case. The guards that matter here — PE-1 on
 * role assignment, PE-3 on changing your own roles or deactivating yourself,
 * PE-5 on stranding the platform — all live there, where the domain tests can
 * see them.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $query = UserModel::query();

        if ($request->boolean('with_deleted')) {
            $query->withTrashed();
        }

        if ($type = $request->validated('type')) {
            $query->where('type', $type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->validated('role')) {
            // Filtering on a role the account holds, by name rather than id,
            // so a caller can ask a question it can read.
            $query->whereExists(function ($q) use ($role): void {
                $q->selectRaw('1')
                    ->from('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->where('roles.name', $role);
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate((int) ($request->validated('per_page') ?? 20));

        // One query for the whole page, rather than one per row — see
        // AdminUserResource for why the resource does not fetch these itself.
        $items = $paginator->items();
        $roleNames = $this->authorization->rolesOfMany(
            array_map(static fn (UserModel $u): string => (string) $u->id, $items)
        );

        foreach ($items as $user) {
            $user->setAttribute('role_names', $roleNames[(string) $user->id] ?? []);
        }

        return response()->json([
            'success' => true,
            'data' => AdminUserResource::collection($items),
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
     * One account, with what it can actually do.
     *
     * The effective permission set is answered here and not in the list,
     * because here it is one resolution for a question someone asked about
     * one person.
     */
    public function show(string $id): JsonResponse
    {
        $user = UserModel::withTrashed()->findOrFail($id);
        $user->setAttribute('role_names', $this->authorization->rolesOf($user));

        return response()->json([
            'success' => true,
            'data' => array_merge(
                (new AdminUserResource($user))->toArray(request()),
                ['permissions' => $this->authorization->permissionsOf($user)],
            ),
        ]);
    }

    public function syncRoles(
        SyncUserRolesRequest $request,
        string $id,
        SyncUserRolesUseCase $syncRoles
    ): JsonResponse {
        try {
            $syncRoles->execute($id, $request->validated('roles'), (string) $request->user()->id);
        } catch (SelfRoleChangeException $e) {
            return $this->refusal('SELF_ROLE_CHANGE', $e->getMessage(), 403);
        } catch (PrivilegeEscalationException $e) {
            return $this->refusal('PRIVILEGE_ESCALATION', $e->getMessage(), 403);
        } catch (LastSystemRoleHolderException $e) {
            return $this->refusal('LAST_SYSTEM_ROLE_HOLDER', $e->getMessage(), 409);
        }

        return $this->fresh($id, __('User roles updated.'));
    }

    public function activate(Request $request, string $id, ActivateUserUseCase $activate): JsonResponse
    {
        $activate->execute($id, (string) $request->user()->id);

        return $this->fresh($id, __('User activated.'));
    }

    public function deactivate(Request $request, string $id, DeactivateUserUseCase $deactivate): JsonResponse
    {
        try {
            $deactivate->execute($id, (string) $request->user()->id);
        } catch (SelfDeactivationException $e) {
            return $this->refusal('SELF_DEACTIVATION', $e->getMessage(), 403);
        } catch (LastSystemRoleHolderException $e) {
            return $this->refusal('LAST_SYSTEM_ROLE_HOLDER', $e->getMessage(), 409);
        }

        return $this->fresh($id, __('User deactivated.'));
    }

    private function fresh(string $id, string $message): JsonResponse
    {
        $user = UserModel::withTrashed()->findOrFail($id);
        $user->setAttribute('role_names', $this->authorization->rolesOf($user));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new AdminUserResource($user),
        ]);
    }

    private function refusal(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
