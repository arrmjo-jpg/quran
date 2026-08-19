import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  AdminUser,
  AdminUserDetail,
  CreateUserPayload,
  SyncUserRolesPayload,
  UpdateUserPayload,
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
    if (filters.with_deleted) params.with_deleted = true;

    const { data } = await http.get<PaginatedUsers>('/admin/users', { params });

    return { users: data.data, total: data.meta.pagination.total };
  },

  async getUser(id: string): Promise<AdminUserDetail> {
    const { data } = await http.get<ApiSuccess<AdminUserDetail>>(`/admin/users/${id}`);
    return data.data;
  },

  /**
   * Creates a pending account and sends its invitation.
   *
   * The response deliberately carries no token or link: the administrator
   * creating the account must not be able to claim it (ADR-016 D14), so the
   * invitation reaches the invitee's mailbox and nowhere else.
   */
  async createUser(payload: CreateUserPayload): Promise<AdminUser> {
    const { data } = await http.post<ApiSuccess<AdminUser>>('/admin/users', payload);
    return data.data;
  },

  async updateUser({ id, name, locale }: UpdateUserPayload): Promise<AdminUser> {
    const { data } = await http.patch<ApiSuccess<AdminUser>>(`/admin/users/${id}`, { name, locale });
    return data.data;
  },

  /** Soft delete. The account keeps its roles and can be restored. */
  async deleteUser(id: string): Promise<void> {
    await http.delete(`/admin/users/${id}`);
  },

  async restoreUser(id: string): Promise<AdminUser> {
    const { data } = await http.patch<ApiSuccess<AdminUser>>(`/admin/users/${id}/restore`);
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
