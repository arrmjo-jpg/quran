<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Events\RoleDeleted;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

/**
 * Deletes a custom role.
 *
 * The event is built BEFORE the delete, because the row and its grants
 * are gone afterwards and an audit entry carrying only an id would be
 * unreadable a week later.
 *
 * Deleting a role that users currently hold is permitted and ordinary.
 */
final readonly class DeleteRoleUseCase
{
    public function __construct(
        private RoleRepositoryContract $roles,
        private EffectivePermissionResolver $permissions,
    ) {}

    public function execute(string $roleId, ?string $byUserId = null): void
    {
        DB::transaction(function () use ($roleId, $byUserId): void {
            $id = new RoleId($roleId);
            $role = $this->roles->findOrFail($id);

            $role->assertDeletable();

            $event = new RoleDeleted(
                roleId: $role->id->value,
                name: $role->getName(),
                permissions: $role->getPermissionNames(),
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            );

            // Writer 3 of 3 (ADR-015 §4.6), and the only one where
            // ordering is load-bearing: the holders must be captured
            // BEFORE the pivot rows go. Asking afterwards finds nobody
            // and leaves every former holder authorized by a stale cache
            // entry until the TTL expires.
            $holders = $this->permissions->holdersOf($id);

            $this->roles->delete($id);

            foreach ($holders as $userId) {
                $this->permissions->forget(new UserId($userId));
            }

            event($event);
        });
    }
}
