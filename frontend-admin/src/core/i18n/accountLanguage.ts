import i18n, { SUPPORTED_LANGUAGES, applyDirection, type LanguageCode } from '@/core/config/i18n';
import { http } from '@/core/api/http';
import { STORAGE_KEYS } from '@/core/constants';

/**
 * The account's language — ADR-019 D2, D3, D10.
 *
 * `users.preferred_locale` is the source of truth. It always was in the
 * database, in the API and in the administrator-facing form; only the browser
 * disagreed, reading a `localStorage` key that nothing on the server knew
 * about. So an administrator who set a colleague's language changed nothing
 * the colleague would ever see.
 *
 * WHY THIS IS A SEPARATE MODULE FROM `config/i18n.ts`. That file runs at
 * import time to initialise i18next, before React mounts and before any
 * request can have been made. It must stay free of HTTP for that reason: the
 * boot path cannot wait for the network, and giving it a dependency on the
 * API client would invite exactly that. This module is the part that talks to
 * the server, and nothing imports it during boot.
 */

function isSupported(code: string | null | undefined): code is LanguageCode {
  return SUPPORTED_LANGUAGES.some((l) => l.code === code);
}

/**
 * Apply a language to the running interface, without touching the server.
 *
 * `localStorage` is written here too, and that is the point of D3 rather than
 * a leftover: it is the value the NEXT boot reads synchronously. Keeping it in
 * step with the account is what stops the interface flashing the previous
 * language on every reload.
 */
export function applyLanguage(code: LanguageCode): void {
  i18n.changeLanguage(code);
  applyDirection(code);

  try {
    localStorage.setItem(STORAGE_KEYS.language, code);
  } catch {
    // Private mode, or storage disabled. The language still applies to this
    // session; only the head start on the next boot is lost.
  }
}

/**
 * Persist the choice to the account — ADR-019 D10.
 *
 * Reuses `PATCH /me`, which already validates `preferred_locale` against
 * `in:ar,en,es` and already persists it through UpdateUserProfileUseCase. No
 * endpoint and no permission was added for this: the route is the
 * self-service write path and holding the account is the authorisation.
 *
 * The caller applies the language first and awaits this second, so the
 * interface responds immediately and a failed write does not leave the user
 * staring at an unchanged screen. The rejection is surfaced, not swallowed —
 * a language that silently fails to save is the defect this epic exists to
 * remove, in a new costume.
 */
export async function persistLanguage(code: LanguageCode): Promise<void> {
  await http.patch('/me', { preferred_locale: code });
}

/**
 * Bring the interface in line with the account — ADR-019 D3.
 *
 * Called wherever an account becomes known: after either login path, and on
 * mount for a session restored from storage. It needs no request of its own,
 * because the login response and the stored user object both already carry
 * `preferred_locale`.
 *
 * Does nothing when the value is absent or unsupported. An account created
 * before this shipped, or one holding a language the panel cannot render,
 * keeps whatever the browser was using rather than being forced somewhere
 * with no translations.
 */
export function reconcileLanguage(preferred: string | null | undefined): void {
  if (!isSupported(preferred)) {
    return;
  }

  if (i18n.language === preferred) {
    return;
  }

  applyLanguage(preferred);
}
