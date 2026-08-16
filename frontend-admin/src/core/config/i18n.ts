import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { STORAGE_KEYS } from '@/core/constants';

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
 * Translation files register themselves.
 *
 * Every locales/<lang>/<namespace>.json is picked up by its path, so adding
 * a screen's translations means dropping the file in and nothing else, and
 * adding a fourth language means creating locales/<lang>/ and nothing else.
 *
 * The previous arrangement needed the same file named in two places — an
 * import plus an entry in `ns` — and missing either one left t() silently
 * returning the raw key. That is not a hypothetical: the seasons namespace
 * shipped unregistered and the screen rendered "title", "YEAR" and "SLUG"
 * to the admin until it was noticed.
 */
const modules = import.meta.glob<{ default: Record<string, string> }>(
  '../../locales/*/*.json',
  { eager: true },
);

const resources: Record<string, Record<string, Record<string, string>>> = {};

for (const [path, module] of Object.entries(modules)) {
  // ../../locales/ar/seasons.json -> ['ar', 'seasons']
  const match = path.match(/\/locales\/([^/]+)\/([^/]+)\.json$/);

  if (!match) continue;

  const [, language, namespace] = match;

  resources[language] ??= {};
  resources[language][namespace] = module.default;
}

/** Derived from the files themselves, so it can never fall out of step. */
const namespaces = [
  ...new Set(Object.values(resources).flatMap((byNamespace) => Object.keys(byNamespace))),
];

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

const lng = initialLanguage();

i18n.use(initReactI18next).init({
  resources,
  lng,
  fallbackLng: DEFAULT_LANGUAGE,
  ns: namespaces,
  defaultNS: 'common',
  interpolation: {
    escapeValue: false,
  },
});

applyDirection(lng);

export default i18n;
