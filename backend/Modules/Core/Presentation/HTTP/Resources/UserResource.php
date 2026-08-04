<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Domain\Entities\User;

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
                'type' => 'admin',
                'status' => $resource->isActive() ? 'active' : 'inactive',
                'is_active' => $resource->isActive(),
                'email_verified' => true,
                'avatar' => null,
                'roles' => ['admin'],
                'permissions' => ['*'],
                'preferred_locale' => (string) $resource->getPreferredLocale(),
            ];
        }

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'email' => $resource->email,
            'type' => $resource->type ?? 'admin',
            'status' => ($resource->is_active ?? true) ? 'active' : 'inactive',
            'is_active' => (bool) ($resource->is_active ?? true),
            'email_verified' => true,
            'avatar' => null,
            'roles' => [$resource->type ?? 'admin'],
            'permissions' => ['*'],
            'preferred_locale' => $resource->preferred_locale ?? 'ar',
        ];
    }
}
