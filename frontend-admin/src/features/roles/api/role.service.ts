import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { PermissionKey } from '@/core/permissions';
import type {
  CreateRolePayload,
  RenameRolePayload,
  Role,
  SyncRolePermissionsPayload,
} from '../types';

export const roleService = {
  async getRoles(): Promise<Role[]> {
    const { data } = await http.get<ApiSuccess<Role[]>>('/admin/roles');
    return data.data;
  },

  /**
   * The catalogue as the server defines it, flat. Grouping is derived from
   * the names by whoever displays them — see groupPermissions().
   */
  async getPermissions(): Promise<PermissionKey[]> {
    const { data } = await http.get<ApiSuccess<{ permissions: PermissionKey[] }>>(
      '/admin/permissions'
    );
    return data.data.permissions;
  },

  async createRole(payload: CreateRolePayload): Promise<Role> {
    const { data } = await http.post<ApiSuccess<Role>>('/admin/roles', payload);
    return data.data;
  },

  async renameRole({ id, name }: RenameRolePayload): Promise<Role> {
    const { data } = await http.patch<ApiSuccess<Role>>(`/admin/roles/${id}`, { name });
    return data.data;
  },

  async syncPermissions({ id, permissions }: SyncRolePermissionsPayload): Promise<Role> {
    const { data } = await http.patch<ApiSuccess<Role>>(`/admin/roles/${id}/permissions`, {
      permissions,
    });
    return data.data;
  },

  async deleteRole(id: string): Promise<void> {
    await http.delete(`/admin/roles/${id}`);
  },
};
