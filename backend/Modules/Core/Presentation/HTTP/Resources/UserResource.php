<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\ValueObjects\UserType;

/**
 * NOTE (ADR-015 §5): `roles` and `permissions` below are still derived
 * from `type` rather than from real role/permission data — `permissions`
 * in particular reports a literal `['*']` for admins. That is a known
 * defect this epic deliberately does not fix: telling the truth requires
 * the effective-permission resolver, which does not exist until the
 * Role↔Permission and User↔Role epics. Preserving the current shape here
 * keeps this epic behaviour-neutral, as scoped.
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User|object $resource */
        $resource = $this->resource;

        if ($resource instanceof User) {
            return [
                'id' => $resource->id->value,
                'name' => $resource->getName(),
                'email' => (string) $resource->getEmail(),
                'type' => $resource->getType()->value,
                'status' => $resource->isActive() ? 'active' : 'inactive',
                'is_active' => $resource->isActive(),
                'email_verified' => true,
                'avatar' => null,
                'roles' => [$resource->getType()->value],
                'permissions' => $resource->isAdmin() ? ['*'] : [],
                'preferred_locale' => (string) $resource->getPreferredLocale(),
            ];
        }

        $type = $resource->type ?? UserType::CONTESTANT;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'email' => $resource->email,
            'type' => $type,
            'status' => ($resource->is_active ?? true) ? 'active' : 'inactive',
            'is_active' => (bool) ($resource->is_active ?? true),
            'email_verified' => true,
            'avatar' => null,
            'roles' => [$type],
            'permissions' => $type === UserType::ADMIN ? ['*'] : [],
            'preferred_locale' => $resource->preferred_locale ?? 'ar',
        ];
    }
}
