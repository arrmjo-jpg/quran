import axios from 'axios';
import i18n from '@/core/config/i18n';

export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

/**
 * Extract a human-readable error message from any API error.
 *
 * The default is resolved on call, not as a parameter default: a module-level
 * default would be evaluated once at import and pin the message to the boot
 * language. Nearly every caller passes its own fallback anyway; this is what
 * the handful that don't end up showing.
 */
export function extractErrorMessage(error: unknown, fallback?: string): string {
  fallback ??= i18n.t('common:unexpected_error');

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
