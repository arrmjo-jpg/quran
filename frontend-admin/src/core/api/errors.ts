import axios from 'axios';

export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

/** Extract a human-readable error message from any API error */
export function extractErrorMessage(error: unknown, fallback = 'حدث خطأ غير متوقع'): string {
  if (axios.isAxiosError(error)) {
    // Our API's actual error envelope is {success, error: {code, message}} —
    // top-level "message" only appears on Laravel's own unhandled/stock
    // responses. Check the real shape first, top-level as a fallback.
    const data = error.response?.data as { message?: string; error?: { message?: string } } | undefined;
    return data?.error?.message ?? data?.message ?? fallback;
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
