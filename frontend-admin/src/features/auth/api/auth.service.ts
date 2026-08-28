import axios from 'axios';
import { http } from '@/core/api/http';
import { STORAGE_KEYS } from '@/core/constants';
import { env } from '@/core/config/env';
import type { ApiSuccess } from '@/core/types';

export interface LoginPayload {
  email:    string;
  password: string;
}

export interface AuthUserData {
  id:          string;
  name:        string;
  email:       string;
  type:        string;
  /** Display only — the names of the roles held. Never an authorization input. */
  roles:       string[];
  /** What UserResource resolved through those roles. The only basis for a UI decision. */
  permissions: string[];
  /** This account's own MFA state — ADR-018 D4. Never anyone else's. */
  mfa_enabled?: boolean;
}

export interface AuthResponseData {
  token: string;
  user:  AuthUserData;
}

export interface MfaChallengeResponseData {
  mfa_required:    true;
  challenge_token: string;
}

export type LoginResult = AuthResponseData | MfaChallengeResponseData;

export function isMfaChallenge(result: LoginResult): result is MfaChallengeResponseData {
  return 'mfa_required' in result && result.mfa_required === true;
}

/**
 * The trust grant for this browser — ADR-018 D5.
 *
 * Held in localStorage because login must present it before any session
 * exists. Read defensively: a browser with storage disabled simply gets the
 * MFA challenge, which is the correct fallback in every case.
 */
export function readTrustToken(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEYS.trustToken);
  } catch {
    return null;
  }
}

export function storeTrustToken(token: string): void {
  try {
    localStorage.setItem(STORAGE_KEYS.trustToken, token);
  } catch {
    // Not fatal: the device stays trusted server-side, this browser just
    // cannot prove it and will be challenged as usual.
  }
}

export function clearTrustToken(): void {
  try {
    localStorage.removeItem(STORAGE_KEYS.trustToken);
  } catch {
    /* nothing to clear */
  }
}

export const authService = {
  async login(payload: LoginPayload): Promise<LoginResult> {
    // Presented as a header rather than in the body: it is a credential, not
    // part of the login form, and the server verifies it against this
    // account's live grants only (ADR-018 D5).
    const trustToken = readTrustToken();

    const { data } = await http.post<ApiSuccess<LoginResult>>(
      '/admin/auth/login',
      payload,
      trustToken ? { headers: { 'X-Device-Trust-Token': trustToken } } : undefined,
    );
    return data.data;
  },

  /**
   * Completes the MFA challenge started by login(). Uses a bare axios call
   * (not the shared `http` instance) so the challenge_token is sent
   * explicitly — the shared instance's interceptor always injects
   * whatever's in localStorage, which is the wrong token at this point in
   * the flow (the user isn't logged in yet).
   */
  async completeMfaChallenge(challengeToken: string, code: string): Promise<AuthResponseData> {
    const { data } = await axios.post<ApiSuccess<AuthResponseData>>(
      `${env.apiBaseUrl}/admin/auth/login/mfa-challenge`,
      { code },
      { headers: { Authorization: `Bearer ${challengeToken}`, Accept: 'application/json' } },
    );
    return data.data;
  },

  async getMe(): Promise<AuthUserData> {
    const { data } = await http.get<ApiSuccess<AuthUserData>>('/admin/auth/me');
    return data.data;
  },
};
