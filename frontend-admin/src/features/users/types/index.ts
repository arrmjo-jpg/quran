export type UserType = 'admin' | 'contestant';

/**
 * One derived state rather than a combination the client has to get right.
 * `pending_activation` and `deactivated` are both is_active === false, and a
 * screen that conflated them would tell an administrator that a colleague they
 * had just invited was disabled.
 */
export type UserStatus = 'active' | 'pending_activation' | 'deactivated' | 'deleted';

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  type: UserType;
  status: UserStatus;
  is_active: boolean;
  is_deleted: boolean;
  mfa_enabled: boolean;
  preferred_locale: string;
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
  /** Deleted accounts are hidden unless asked for — see the API's findWithTrashed. */
  with_deleted?: boolean;
}

export interface CreateUserPayload {
  email: string;
  name: string;
  /** Role ids. Chosen at creation so PE-1 refuses an over-grant at the form. */
  roles?: string[];
  locale?: string;
}

export interface UpdateUserPayload {
  id: string;
  name: string;
  locale?: string;
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
