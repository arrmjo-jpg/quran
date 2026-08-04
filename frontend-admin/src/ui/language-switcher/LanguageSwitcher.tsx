import React from 'react';
import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';
import { STORAGE_KEYS } from '@/core/constants';

const languages = [
  { code: 'ar', label: 'العربية', dir: 'rtl' },
  { code: 'en', label: 'English', dir: 'ltr' },
  { code: 'es', label: 'Español', dir: 'ltr' },
] as const;

export default function LanguageSwitcher(): React.JSX.Element {
  const { i18n } = useTranslation();

  const handleLanguageChange = (langCode: 'ar' | 'en' | 'es', dir: 'rtl' | 'ltr') => {
    i18n.changeLanguage(langCode);
    document.documentElement.dir = dir;
    document.documentElement.lang = langCode;
    localStorage.setItem(STORAGE_KEYS.language, langCode);
  };

  return (
    <div className="relative inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
      <Globe className="w-4 h-4 text-slate-500 mx-1" />
      {languages.map((lang) => (
        <button
          key={lang.code}
          onClick={() => handleLanguageChange(lang.code, lang.dir)}
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
