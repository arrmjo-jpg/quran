import axios from 'axios';
import i18n from '@/core/config/i18n';
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
 * a log and wrong for this UI, so known codes get a written-out explanation
 * and anything unrecognised falls back to the API's message.
 */
const MESSAGE_KEYS: Record<string, string> = {
  STAGE_IN_USE: 'err_stage_in_use',
  SEASON_ALREADY_FROZEN: 'err_season_frozen',
  INVALID_STAGE_ORDER: 'err_invalid_order',
};

export function extractStageErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const code = (error.response?.data as { error?: { code?: string } } | undefined)?.error?.code;

    // Resolved at error time, not in the map: this module is imported once,
    // and finished strings there would be stuck on the boot language.
    if (code && MESSAGE_KEYS[code]) {
      return i18n.t(`stages:${MESSAGE_KEYS[code]}`);
    }
  }

  return extractErrorMessage(error, fallback);
}
