import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  AdminUser,
  AdminUserDetail,
  SyncUserRolesPayload,
  UserListFilters,
  UserListResult,
} from '../types';

interface PaginatedUsers {
  success: boolean;
  data: AdminUser[];
  meta: { pagination: { total: number } };
}

export const userService = {
  async getUsers(filters: UserListFilters): Promise<UserListResult> {
    const params: Record<string, string | number | boolean> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set. An empty `search=` would filter on the empty
    // string rather than mean "no filter".
    if (filters.search) params.search = filters.search;
    if (filters.type) params.type = filters.type;
    if (filters.is_active !== undefined) params.is_active = filters.is_active;

    const { data } = await http.get<PaginatedUsers>('/admin/users', { params });

    return { users: data.data, total: data.meta.pagination.total };
  },

  async getUser(id: string): Promise<AdminUserDetail> {
    const { data } = await http.get<ApiSuccess<AdminUserDetail>>(`/admin/users/${id}`);
    return data.data;
  },

  async syncRoles({ id, roles }: SyncUserRolesPayload): Promise<AdminUser> {
    const { data } = await http.patch<ApiSuccess<AdminUser>>(`/admin/users/${id}/roles`, { roles });
    return data.data;
  },

  async activate(id: string): Promise<AdminUser> {
    const { data } = await http.patch<ApiSuccess<AdminUser>>(`/admin/users/${id}/activate`);
    return data.data;
  },

  async deactivate(id: string): Promise<AdminUser> {
    const { data } = await http.patch<ApiSuccess<AdminUser>>(`/admin/users/${id}/deactivate`);
    return data.data;
  },
};
