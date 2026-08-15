import axios from 'axios';
import { extractErrorMessage } from '@/core/api/errors';

/**
 * The stage endpoints return two different 409s that mean very different
 * things to an admin, so the status code alone is not enough to explain
 * what happened:
 *
 *   STAGE_IN_USE           — something already references this stage, and
 *                            it can never be deleted now
 *   SEASON_ALREADY_FROZEN  — registration opened, so nothing about the
 *                            season's stages may change any more
 *
 * The API's own messages are English and embed raw UUIDs, which is fine for
 * a log and wrong for this UI, so known codes get a written-out Arabic
 * explanation and anything unrecognised falls back to the API's message.
 */
const MESSAGES: Record<string, string> = {
  STAGE_IN_USE:
    'لا يمكن حذف هذه المرحلة لأن هناك بيانات مرتبطة بها (قواعد تحكيم، تكليفات محكمين، بث، طلبات، أو نتائج). الحذف متاح فقط للمراحل التي لم تُستخدم بعد.',
  SEASON_ALREADY_FROZEN:
    'هذا الموسم مجمّد لأن التسجيل فُتح فيه، ولا يمكن تعديل مراحله بعد الآن.',
  INVALID_STAGE_ORDER:
    'ترتيب المراحل المُرسل غير صالح. يجب أن يتضمن جميع مراحل الموسم مرة واحدة لكل مرحلة.',
};

export function extractStageErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const code = (error.response?.data as { error?: { code?: string } } | undefined)?.error?.code;

    if (code && MESSAGES[code]) {
      return MESSAGES[code];
    }
  }

  return extractErrorMessage(error, fallback);
}
