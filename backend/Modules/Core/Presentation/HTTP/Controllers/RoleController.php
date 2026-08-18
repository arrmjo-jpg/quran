<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\DeleteRoleUseCase;
use Modules\Core\Application\UseCases\RenameRoleUseCase;
use Modules\Core\Application\UseCases\SyncRolePermissionsUseCase;
use Modules\Core\Domain\Exceptions\PrivilegeEscalationException;
use Modules\Core\Domain\Exceptions\SystemRoleImmutableException;
use Modules\Core\Domain\Exceptions\UnknownPermissionException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Presentation\HTTP\Requests\CreateRoleRequest;
use Modules\Core\Presentation\HTTP\Requests\RenameRoleRequest;
use Modules\Core\Presentation\HTTP\Requests\SyncRolePermissionsRequest;
use Modules\Core\Presentation\HTTP\Resources\RoleResource;
use RuntimeException;

/**
 * The role editor's API — ADR-015 §4.
 *
 * Every write goes through a use case, which is where PE-1, PE-2, system-role
 * immutability and cache invalidation live. This controller adds no rule of
 * its own; it translates domain refusals into status codes and nothing more.
 * A guard implemented here would be a guard the domain tests cannot see.
 *
 * Reads go through the repository rather than Eloquent, because
 * RoleRepositoryContract::all() already exists and roles number about six —
 * there is nothing to paginate and no query to optimise.
 */
final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepositoryContract $roles,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($this->roles->all()),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RoleResource($this->roles->findOrFail(new RoleId($id))),
        ]);
    }

    public function store(CreateRoleRequest $request, CreateRoleUseCase $createRole): JsonResponse
    {
        try {
            $role = $createRole->execute(
                name: $request->validated('name'),
                permissionNames: $request->validated('permissions', []),
                byUserId: (string) $request->user()->id,
            );
        } catch (UnknownPermissionException $e) {
            return $this->unknownPermission($e);
        } catch (PrivilegeEscalationException $e) {
            return $this->privilegeEscalation($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidName($e);
        } catch (RuntimeException $e) {
            // The name was free when validation ran and taken by the time
            // the use case looked. Rare, but the alternative is a 500 for
            // something the caller can act on.
            return $this->nameTaken($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Role created.'),
            'data' => new RoleResource($role),
        ], 201);
    }

    public function rename(RenameRoleRequest $request, string $id, RenameRoleUseCase $renameRole): JsonResponse
    {
        try {
            $role = $renameRole->execute($id, $request->validated('name'), (string) $request->user()->id);
        } catch (SystemRoleImmutableException $e) {
            return $this->systemRole($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidName($e);
        } catch (RuntimeException $e) {
            return $this->nameTaken($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Role renamed.'),
            'data' => new RoleResource($role),
        ]);
    }

    public function syncPermissions(
        SyncRolePermissionsRequest $request,
        string $id,
        SyncRolePermissionsUseCase $syncPermissions
    ): JsonResponse {
        try {
            $role = $syncPermissions->execute(
                roleId: $id,
                permissionNames: $request->validated('permissions'),
                byUserId: (string) $request->user()->id,
            );
        } catch (UnknownPermissionException $e) {
            return $this->unknownPermission($e);
        } catch (PrivilegeEscalationException $e) {
            return $this->privilegeEscalation($e);
        } catch (SystemRoleImmutableException $e) {
            return $this->systemRole($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Role permissions updated.'),
            'data' => new RoleResource($role),
        ]);
    }

    public function destroy(Request $request, string $id, DeleteRoleUseCase $deleteRole): JsonResponse
    {
        try {
            $deleteRole->execute($id, (string) $request->user()->id);
        } catch (SystemRoleImmutableException $e) {
            return $this->systemRole($e);
        }

        return response()->json([
            'success' => true,
            'message' => __('Role deleted.'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Domain refusals, as status codes
    |--------------------------------------------------------------------------
    */

    private function unknownPermission(UnknownPermissionException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'UNKNOWN_PERMISSION', 'message' => $e->getMessage()],
        ], 422);
    }

    /**
     * 403 and not 422: the request is well formed and the names are real.
     * What is refused is the actor, so this is an authorization answer.
     */
    private function privilegeEscalation(PrivilegeEscalationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'PRIVILEGE_ESCALATION', 'message' => $e->getMessage()],
        ], 403);
    }

    private function systemRole(SystemRoleImmutableException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'SYSTEM_ROLE_IMMUTABLE', 'message' => $e->getMessage()],
        ], 409);
    }

    private function invalidName(InvalidArgumentException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVALID_ROLE_NAME', 'message' => $e->getMessage()],
        ], 422);
    }

    private function nameTaken(RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'ROLE_NAME_TAKEN', 'message' => $e->getMessage()],
        ], 409);
    }
}
