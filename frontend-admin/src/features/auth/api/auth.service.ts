import axios from 'axios';
import { http } from '@/core/api/http';
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

export const authService = {
  async login(payload: LoginPayload): Promise<LoginResult> {
    const { data } = await http.post<ApiSuccess<LoginResult>>('/admin/auth/login', payload);
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
