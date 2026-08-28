import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios';
import { env } from '@/core/config/env';
import { STORAGE_KEYS } from '@/core/constants';

// ── Axios Instance ────────────────────────────────────────────────────────────
export const http: AxiosInstance = axios.create({
  baseURL: env.apiBaseUrl,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 30_000,
});

// ── Device identity ──────────────────────────────────────────────────────────
/**
 * A stable id for this browser — ADR-018 D7.
 *
 * `AuditLoggingMiddleware` has read `X-Device-ID` since it was written, and
 * nothing has ever sent it: the column was NULL on all 2804 audit rows. So a
 * login history could say when and from where, but never on what.
 *
 * NOT A SECURITY CONTROL. It is client-supplied and trivially forged, so
 * nothing authorises on it — it identifies a browser for display and
 * correlation. The credential that actually skips an MFA challenge is the
 * trust token below, which the server issues and verifies by hash.
 *
 * Generated once and kept. If storage is unavailable or cleared, a new id is
 * minted: the consequence is a device that looks new in the log, which is
 * accurate, since from the browser's side it is.
 */
export function deviceId(): string {
  try {
    const existing = localStorage.getItem(STORAGE_KEYS.deviceId);
    if (existing) return existing;

    const fresh = crypto.randomUUID();
    localStorage.setItem(STORAGE_KEYS.deviceId, fresh);
    return fresh;
  } catch {
    // Private mode, or storage disabled. An ephemeral id is better than a
    // request that fails because it could not name itself.
    return crypto.randomUUID();
  }
}

// ── Request Interceptor: inject Bearer token and device identity ─────────────
http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem(STORAGE_KEYS.token);
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  if (config.headers) {
    config.headers['X-Device-ID'] = deviceId();
  }

  return config;
});

// ── Response Interceptor: handle 401 forced logout ──────────────────────────
let _onForcedLogout: (() => void) | null = null;

export function registerForcedLogout(handler: () => void): void {
  _onForcedLogout = handler;
}

http.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error) => {
    if (error?.response?.status === 401) {
      localStorage.removeItem(STORAGE_KEYS.token);
      localStorage.removeItem(STORAGE_KEYS.user);
      _onForcedLogout?.();
    }
    return Promise.reject(error);
  },
);
