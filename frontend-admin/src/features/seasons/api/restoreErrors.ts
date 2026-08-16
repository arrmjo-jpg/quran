import axios from 'axios';
import i18n from '@/core/config/i18n';
import { extractErrorMessage } from '@/core/api/errors';

/**
 * The restore endpoint refuses with several different 409s, and which one
 * came back is the whole message: "you cannot undo this" and "you cannot
 * undo this *yet, because X references it*" are different situations for
 * an admin. The API's own text is English and lists raw table names, so
 * known codes get a written-out explanation of their own.
 */
const MESSAGE_KEYS: Record<string, string> = {
  CANNOT_RESTORE_FROZEN_SEASON: 'err_restore_frozen',
  CANNOT_RESTORE_SEASON_WITH_SNAPSHOTS: 'err_restore_snapshots',
  CANNOT_RESTORE_SEASON_WITH_DEPENDENTS: 'err_restore_dependents',
  CANNOT_RESTORE_ACTIVE_SEASON: 'err_restore_active',
  SEASON_NOT_ARCHIVED: 'err_restore_not_archived',
};

/** Table/count pairs the API sends back, e.g. "applications: 3". */
export function extractRestoreBlockers(error: unknown): string[] {
  if (axios.isAxiosError(error)) {
    const details = (error.response?.data as { error?: { details?: unknown } } | undefined)?.error?.details;

    if (Array.isArray(details)) {
      return details.filter((d): d is string => typeof d === 'string');
    }
  }

  return [];
}

export function extractRestoreErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const code = (error.response?.data as { error?: { code?: string } } | undefined)?.error?.code;

    // Resolved here rather than in the map above: this module is imported
    // once, and a map of finished strings would be stuck on the language
    // that happened to be active then.
    if (code && MESSAGE_KEYS[code]) {
      const message = i18n.t(`seasons:${MESSAGE_KEYS[code]}`);
      const blockers = extractRestoreBlockers(error);
      const separator = i18n.t('seasons:blockers_separator');

      return blockers.length > 0 ? `${message} (${blockers.join(separator)})` : message;
    }
  }

  return extractErrorMessage(error, fallback);
}
