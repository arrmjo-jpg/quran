// Core environment configuration — reads VITE_API_BASE_URL from .env
export const env = {
  apiBaseUrl: (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? 'http://localhost:8080/api/v1',
} as const;
