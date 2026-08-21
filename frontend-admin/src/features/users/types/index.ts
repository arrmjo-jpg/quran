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

/**
 * The contestant record behind an account — ADR-016 D22.
 *
 * Three fields, and there is no fourth. The same rule that keeps `email` off
 * a contestant screen keeps a national ID, a date of birth and a phone
 * number off this one: Identity 360 shows what identifies, explains or
 * links, never what describes.
 */
export interface LinkedContestant {
  id:         string;
  full_name:  string;
  /** Explains an account whose owner is no longer competing. */
  is_deleted: boolean;
}

/**
 * The single-account view adds the effective permission set, and since
 * Story 3 the contestant behind the account.
 *
 * `contestant: null` with an empty `withheld` means this account is not a
 * contestant. `contestant: null` with `withheld: ['contestant']` means the
 * reader lacks contestants.view and is not being told either way. The two
 * must never be rendered the same, which is the whole reason `withheld`
 * exists rather than a bare null (D20).
 */
export interface AdminUserDetail extends AdminUser {
  permissions: string[];
  contestant:  LinkedContestant | null;
  withheld:    'contestant'[];
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
