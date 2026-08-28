import { http } from '@/core/api/http';
import { storeTrustToken, clearTrustToken } from '@/features/auth/api/auth.service';
import type { ApiSuccess } from '@/core/types';
import type {
  LoginHistoryFilters,
  LoginHistoryResult,
  TrustedDevice,
  TrustedDeviceGrant,
  LoginAttempt,
} from '../types';

interface LoginHistoryResponse {
  success: boolean;
  data:    LoginAttempt[];
  users:   Record<string, string>;
  meta:    { pagination: { total: number; last_page: number } };
}

export const securityService = {
  /**
   * `GET /admin/security/login-history`, behind `security.view` — ADR-018 D2/D3.
   *
   * Deliberately not behind `audit.view`: that permission means the activity
   * feed, and one grant covering both screens would confer a view of every
   * login attempt on the platform to anyone given the other.
   */
  async getLoginHistory(filters: LoginHistoryFilters): Promise<LoginHistoryResult> {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set — an empty value would filter on the empty string
    // rather than mean "no filter", and the server refuses an outcome outside
    // its vocabulary rather than silently matching nothing.
    if (filters.user_id) params.user_id = filters.user_id;
    if (filters.outcome) params.outcome = filters.outcome;
    if (filters.ip) params.ip = filters.ip;
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    const { data } = await http.get<LoginHistoryResponse>('/admin/security/login-history', { params });

    return {
      attempts: data.data,
      users: data.users,
      total: data.meta.pagination.total,
      lastPage: data.meta.pagination.last_page,
    };
  },

  async getTrustedDevices(): Promise<TrustedDevice[]> {
    const { data } = await http.get<ApiSuccess<TrustedDevice[]>>('/admin/auth/devices');
    return data.data;
  },

  /**
   * Trusting this browser — ADR-018 D5.
   *
   * The token comes back exactly once, so it is stored here rather than left
   * to the caller: a grant whose token was dropped on the floor is a database
   * row that can never be used, and the user would have no way to tell.
   */
  async trustThisDevice(): Promise<TrustedDevice> {
    const { data } = await http.post<ApiSuccess<TrustedDeviceGrant>>('/admin/auth/devices/trust');
    const { trust_token, ...device } = data.data;

    storeTrustToken(trust_token);

    return device;
  },

  /**
   * Turning MFA off — ADR-018 D4.
   *
   * The password is required by the server, not merely asked for here: this is
   * one of the two operations that lower the account's own protection, so an
   * unlocked laptop must not be enough.
   *
   * Every trusted device is revoked server-side as part of this, so the local
   * token is dropped too rather than left pointing at a grant that is gone.
   */
  async disableMfa(password: string): Promise<void> {
    await http.post('/admin/auth/mfa/disable', { password });
    clearTrustToken();
  },

  /** Replaces the existing codes; the old ones stop working (D4). */
  async regenerateRecoveryCodes(password: string): Promise<string[]> {
    const { data } = await http.post<ApiSuccess<{ recovery_codes: string[] }>>(
      '/admin/auth/mfa/recovery-codes',
      { password },
    );
    return data.data.recovery_codes;
  },

  /**
   * Revoking takes effect on the next login, server-side, for any browser.
   * The local token is cleared too when it was this browser's grant, so the
   * two do not disagree about a credential that no longer works.
   */
  async revokeDevice(id: string, wasThisBrowser: boolean): Promise<void> {
    await http.delete(`/admin/auth/devices/${id}`);

    if (wasThisBrowser) {
      clearTrustToken();
    }
  },
};
