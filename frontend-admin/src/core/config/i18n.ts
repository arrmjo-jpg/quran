import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { STORAGE_KEYS } from '@/core/constants';

import arCommon from '@/locales/ar/common.json';
import enCommon from '@/locales/en/common.json';
import esCommon from '@/locales/es/common.json';

import arSeasons from '@/locales/ar/seasons.json';
import enSeasons from '@/locales/en/seasons.json';
import esSeasons from '@/locales/es/seasons.json';

export const SUPPORTED_LANGUAGES = [
  { code: 'ar', label: 'العربية', dir: 'rtl' },
  { code: 'en', label: 'English', dir: 'ltr' },
  { code: 'es', label: 'Español', dir: 'ltr' },
] as const;

export type LanguageCode = (typeof SUPPORTED_LANGUAGES)[number]['code'];
export type Direction = (typeof SUPPORTED_LANGUAGES)[number]['dir'];

const DEFAULT_LANGUAGE: LanguageCode = 'ar';

export function directionOf(code: string): Direction {
  return SUPPORTED_LANGUAGES.find((l) => l.code === code)?.dir ?? 'rtl';
}

/**
 * The language the admin last chose. LanguageSwitcher writes it to
 * localStorage; without reading it back here the app booted in Arabic every
 * time and the choice was lost on every reload.
 */
function initialLanguage(): LanguageCode {
  const stored = localStorage.getItem(STORAGE_KEYS.language);

  return SUPPORTED_LANGUAGES.some((l) => l.code === stored)
    ? (stored as LanguageCode)
    : DEFAULT_LANGUAGE;
}

/**
 * Direction belongs on <html>, set once, and nowhere else. Components that
 * hardcode dir="rtl" on their own root override this and pin the layout to
 * RTL no matter which language is selected.
 */
export function applyDirection(code: string): void {
  document.documentElement.dir = directionOf(code);
  document.documentElement.lang = code;
}

/**
 * Every namespace a screen calls useTranslation() with has to be registered
 * here and listed in `ns` — i18next has no filesystem access, so a
 * translation file that is not imported simply does not exist as far as it
 * is concerned, and t() silently returns the key instead. Adding a new
 * locales/<lang>/<file>.json means adding it in both places below.
 */
const resources = {
  ar: { common: arCommon, seasons: arSeasons },
  en: { common: enCommon, seasons: enSeasons },
  es: { common: esCommon, seasons: esSeasons },
};

const lng = initialLanguage();

i18n.use(initReactI18next).init({
  resources,
  lng,
  fallbackLng: DEFAULT_LANGUAGE,
  ns: ['common', 'seasons'],
  defaultNS: 'common',
  interpolation: {
    escapeValue: false,
  },
});

applyDirection(lng);

export default i18n;
