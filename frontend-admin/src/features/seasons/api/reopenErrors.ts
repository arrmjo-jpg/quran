import axios from 'axios';
import i18n from '@/core/config/i18n';
import { extractErrorMessage } from '@/core/api/errors';

interface ReopenErrorBody {
  error?: {
    code?: string;
    active_season_slug?: string;
    active_season_year?: number;
  };
}

/**
 * Reopening refuses for two reasons that call for different responses from
 * the admin: the season is not in a closed registration state (nothing to
 * do), or another season currently holds the single active slot (close that
 * one first).
 *
 * The API names the blocking season by slug and year precisely so this
 * message can too — telling an admin "another season is active" without
 * saying which one leaves them hunting through the list.
 */
export function extractReopenErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as ReopenErrorBody | undefined;
    const code = body?.error?.code;

    if (code === 'SEASON_NOT_REGISTRATION_CLOSED') {
      return i18n.t('seasons:err_reopen_not_closed');
    }

    if (code === 'ANOTHER_SEASON_IS_ACTIVE') {
      const slug = body?.error?.active_season_slug;
      const year = body?.error?.active_season_year;

      // Three separate sentences rather than one with optional fragments:
      // where the year sits relative to the name is a language's business,
      // not something to assemble from pieces here.
      let named = '';

      if (slug && year) {
        named = i18n.t('seasons:err_reopen_active_named_with_year', { slug, year });
      } else if (slug) {
        named = i18n.t('seasons:err_reopen_active_named', { slug });
      }

      return i18n.t('seasons:err_reopen_another_active', { named });
    }
  }

  return extractErrorMessage(error, fallback);
}
