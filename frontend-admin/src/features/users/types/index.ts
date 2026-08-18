export type UserType = 'admin' | 'contestant';

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  type: UserType;
  is_active: boolean;
  is_deleted: boolean;
  mfa_enabled: boolean;
  /**
   * Role names, not ids. The list reports membership; what the account can
   * actually do is answered per account, because resolving it costs a query
   * per row (see AdminUserResource).
   */
  roles: string[];
  created_at: string | null;
}

/** The single-account view adds the effective permission set. */
export interface AdminUserDetail extends AdminUser {
  permissions: string[];
}

export interface UserListFilters {
  page: number;
  per_page: number;
  search?: string;
  type?: UserType;
  is_active?: boolean;
}

export interface UserListResult {
  users: AdminUser[];
  total: number;
}

export interface SyncUserRolesPayload {
  id: string;
  /** Role ids — the endpoint takes the intended final set, not a delta. */
  roles: string[];
}
