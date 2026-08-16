import axios from 'axios';
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
      return 'لا يمكن إعادة فتح التسجيل إلا لموسم حالته "التسجيل مغلق".';
    }

    if (code === 'ANOTHER_SEASON_IS_ACTIVE') {
      const slug = body?.error?.active_season_slug;
      const year = body?.error?.active_season_year;
      const named = slug ? ` الموسم الفعّال حالياً هو "${slug}"${year ? ` (${year})` : ''}.` : '';

      return `لا يمكن إعادة فتح التسجيل لأن موسماً آخر هو الموسم الفعّال، ولا يسمح النظام بأكثر من موسم فعّال واحد.${named} أغلق تسجيله أولاً ثم أعد المحاولة.`;
    }
  }

  return extractErrorMessage(error, fallback);
}
