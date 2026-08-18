import type { PermissionKey } from '@/core/permissions';

export interface Role {
  id: string;
  name: string;
  /**
   * Read from the aggregate, never computed here. A system role refuses
   * every edit, and the server is the only thing that knows which they are.
   */
  is_system: boolean;
  permissions: PermissionKey[];
  permissions_count: number;
}

export interface CreateRolePayload {
  name: string;
  permissions?: PermissionKey[];
}

export interface RenameRolePayload {
  id: string;
  name: string;
}

export interface SyncRolePermissionsPayload {
  id: string;
  permissions: PermissionKey[];
}
