<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

final class Role
{
    /**
     * @param  array<int, string>  $permissionIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $guardName = 'web',
        private array $permissionIds = [],
    ) {}

    /** @return array<int, string> */
    public function getPermissionIds(): array
    {
        return $this->permissionIds;
    }

    public function grantPermission(string $permissionId): void
    {
        if (! in_array($permissionId, $this->permissionIds, true)) {
            $this->permissionIds[] = $permissionId;
        }
    }
}
