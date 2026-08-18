<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Infrastructure\Database\Models\UserModel;

/**
 * An account as an administrator's list needs to see it.
 *
 * Separate from UserResource on purpose, and the reason is a number rather
 * than a preference: UserResource reports the effective permission set,
 * which costs a resolver call per account. That is right for the contexts it
 * serves — login, /me, a profile update — where there is exactly one
 * account. On a page of twenty it is twenty resolutions to render a column
 * nobody reads at a glance.
 *
 * So the list shows membership (which roles) and state (active, deleted),
 * and the single-account endpoint answers "what can this person actually
 * do?" when someone asks about one person.
 *
 * The role names are read from an attribute the controller sets after one
 * batched query (AuthorizationService::rolesOfMany). Reaching for them here
 * per row would put the N+1 back exactly where this class exists to remove
 * it, so their absence is treated as "not loaded" and reported as none.
 */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var UserModel $user */
        $user = $this->resource;

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'is_active' => (bool) $user->is_active,
            'is_deleted' => $user->deleted_at !== null,
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'roles' => $user->getAttribute('role_names') ?? [],
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
