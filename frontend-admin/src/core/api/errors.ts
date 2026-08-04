import axios from 'axios';

export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

/** Extract a human-readable error message from any API error */
export function extractErrorMessage(error: unknown, fallback = 'حدث خطأ غير متوقع'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string } | undefined;
    return data?.message ?? fallback;
  }
  if (error instanceof Error) return error.message;
  return fallback;
}

/** Extract validation errors map from 422 responses */
export function extractValidationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const data = error.response.data as { errors?: Record<string, string[]> } | undefined;
    return data?.errors ?? {};
  }
  return {};
}
