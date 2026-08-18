<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Domain\Entities\Role;

/**
 * A role as the admin panel needs to see it — ADR-015 §4.
 *
 * `is_system` is read from the aggregate, never computed from a list of
 * names here. Shaabjo made exactly that mistake: its resource decided
 * protection from a hardcoded array, so the UI claimed eight roles were
 * protected while only one actually was, and the badge lied in the
 * direction that matters.
 *
 * The permission names are always included. A role's permission set is
 * the whole reason the editor exists, roles number about six, and each
 * holds at most 81 short strings — so withholding them behind a second
 * request would cost a round trip per row to save nothing.
 */
final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id->value,
            'name' => $role->getName(),
            'is_system' => $role->isSystem(),
            'permissions' => $role->getPermissionNames(),
            'permissions_count' => count($role->getPermissionNames()),
        ];
    }
}
