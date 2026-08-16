import axios from 'axios';
import { extractErrorMessage } from '@/core/api/errors';

/**
 * The restore endpoint refuses with several different 409s, and which one
 * came back is the whole message: "you cannot undo this" and "you cannot
 * undo this *yet, because X references it*" are different situations for
 * an admin. The API's own text is English and lists raw table names, so
 * known codes get a written-out Arabic explanation.
 */
const MESSAGES: Record<string, string> = {
  CANNOT_RESTORE_FROZEN_SEASON:
    'لا يمكن استعادة هذا الموسم لأن التسجيل فُتح فيه وتم تجميد قواعده. الاستعادة متاحة فقط للمواسم التي أُلغيت وهي مسودة ولم تبدأ فعلياً.',
  CANNOT_RESTORE_SEASON_WITH_SNAPSHOTS:
    'لا يمكن استعادة هذا الموسم لأنه يملك نسخة محفوظة من قواعده، ما يعني أنه فُتح للتسجيل فعلاً. الأرشفة هنا سجل دائم ولا يمكن التراجع عنها.',
  CANNOT_RESTORE_SEASON_WITH_DEPENDENTS:
    'لا يمكن استعادة هذا الموسم لأن هناك بيانات مرتبطة به (طلبات مشاركة، نتائج، تكليفات محكمين، أو بث).',
  CANNOT_RESTORE_ACTIVE_SEASON:
    'لا يمكن استعادة هذا الموسم لأنه ما زال مسجّلاً كالموسم الفعّال.',
  SEASON_NOT_ARCHIVED: 'هذا الموسم ليس مؤرشفاً، فلا شيء لاستعادته.',
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

    if (code && MESSAGES[code]) {
      const blockers = extractRestoreBlockers(error);

      return blockers.length > 0 ? `${MESSAGES[code]} (${blockers.join('، ')})` : MESSAGES[code];
    }
  }

  return extractErrorMessage(error, fallback);
}
