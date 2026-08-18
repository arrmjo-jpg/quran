<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Permissions\AuthorizationService;

/**
 * Until the activation epic this resource fabricated both fields it is
 * asked about: `roles` reported the account's *type* ("admin"), and
 * `permissions` reported a literal `['*']`. That was survivable while
 * `type` really was the whole authorization model. It is not survivable
 * now — the server refuses on permissions, so a panel branching on `['*']`
 * would render every button and have each one answered with a 403. A
 * missing button is a smaller failure than one that lies.
 *
 * Both fields are now read from the same place the server decides with.
 *
 * WHY PERMISSIONS AND ROLES COME FROM DIFFERENT QUESTIONS: permissionsOf()
 * returns nothing for a deactivated or deleted account, because it answers
 * "what may this account do right now". rolesOf() still reports the roles,
 * because it answers "what is this account" — a user-management screen has
 * to show that a suspended administrator is still a Competition Manager.
 * Only the first is safe to make a UI decision with.
 *
 * The resolver is queried per response, which is fine here and would not
 * be in a list: every caller of this resource is a single-account auth
 * context (login, /me, profile update, MFA challenge), and the effective
 * set is cached per user anyway.
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User|object $resource */
        $resource = $this->resource;

        if ($resource instanceof User) {
            $model = UserModel::withTrashed()->find($resource->id->value);

            return [
                'id' => $resource->id->value,
                'name' => $resource->getName(),
                'email' => (string) $resource->getEmail(),
                'type' => $resource->getType()->value,
                'status' => $resource->isActive() ? 'active' : 'inactive',
                'is_active' => $resource->isActive(),
                'email_verified' => true,
                'avatar' => null,
                'roles' => $model === null ? [] : self::authorization()->rolesOf($model),
                'permissions' => $model === null ? [] : self::authorization()->permissionsOf($model),
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
            'roles' => $resource instanceof UserModel ? self::authorization()->rolesOf($resource) : [],
            'permissions' => $resource instanceof UserModel ? self::authorization()->permissionsOf($resource) : [],
            'preferred_locale' => $resource->preferred_locale ?? 'ar',
        ];
    }

    private static function authorization(): AuthorizationService
    {
        return app(AuthorizationService::class);
    }
}
