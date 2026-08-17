<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Events\RoleDeleted;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;

/**
 * Deletes a custom role.
 *
 * The event is built BEFORE the delete, because the row and its grants
 * are gone afterwards and an audit entry carrying only an id would be
 * unreadable a week later.
 *
 * Deleting a role that users currently hold is permitted and ordinary —
 * the pivot cascades. Invalidating those users' cached permissions
 * belongs to the epic that introduces the cache; the holders must be
 * resolved before the delete when that lands (ADR-015 §4.6).
 */
final readonly class DeleteRoleUseCase
{
    public function __construct(
        private RoleRepositoryContract $roles,
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

            $this->roles->delete($id);

            event($event);
        });
    }
}
