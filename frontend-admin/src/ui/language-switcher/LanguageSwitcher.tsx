import React from 'react';
import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';
import { SUPPORTED_LANGUAGES, type LanguageCode } from '@/core/config/i18n';
import { applyLanguage, persistLanguage } from '@/core/i18n/accountLanguage';
import { useAuth } from '@/core/auth/AuthContext';

export default function LanguageSwitcher(): React.JSX.Element {
  const { i18n } = useTranslation();
  const { isAuthed, updateUser } = useAuth();

  /**
   * ADR-019 D2: the choice belongs to the account, not the browser.
   *
   * Applied first and saved second, deliberately. The interface responds to
   * the click immediately, and the write happens behind it -- reversing the
   * order would make every language change wait on a round trip.
   *
   * Only saved when there is an account to save it to. This component is
   * currently mounted inside the authenticated shell only, but that is a fact
   * about today's layout rather than a guarantee, and a PATCH /me from a
   * signed-out page would 401 and trip the forced-logout interceptor.
   */
  const handleLanguageChange = (langCode: LanguageCode) => {
    applyLanguage(langCode);

    if (!isAuthed) {
      return;
    }

    // The stored user is a snapshot taken at login, and reconcileLanguage()
    // reads it on the next reload. Without this the snapshot keeps the OLD
    // language and the reload reverts the choice that was just saved -- found
    // by hand, because nothing here is covered by an automated test.
    updateUser({ preferred_locale: langCode });

    void persistLanguage(langCode).catch(() => {
      // Deliberately quiet, and deliberately not reverted. The language the
      // operator asked for is already on screen and in localStorage, so it
      // survives a reload on this browser; what failed is only its spread to
      // their other devices. Undoing the visible change to report a
      // background failure would be the worse trade.
    });
  };

  return (
    <div className="relative inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
      <Globe className="w-4 h-4 mx-1 text-slate-500" />
      {SUPPORTED_LANGUAGES.map((lang) => (
        <button
          key={lang.code}
          onClick={() => handleLanguageChange(lang.code)}
          className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-all ${
            i18n.language === lang.code
              ? 'bg-white dark:bg-slate-900 text-brand-600 dark:text-brand-400 shadow-xs'
              : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'
          }`}
        >
          {lang.label}
        </button>
      ))}
    </div>
  );
}
