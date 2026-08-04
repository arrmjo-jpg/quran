import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios';
import { env } from '@/core/config/env';
import { STORAGE_KEYS } from '@/core/constants';

// ── Axios Instance ────────────────────────────────────────────────────────────
export const http: AxiosInstance = axios.create({
  baseURL: env.apiBaseUrl,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 30_000,
});

// ── Request Interceptor: inject Bearer token ─────────────────────────────────
http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem(STORAGE_KEYS.token);
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
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
