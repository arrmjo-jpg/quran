import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import arCommon from '@/locales/ar/common.json';
import enCommon from '@/locales/en/common.json';
import esCommon from '@/locales/es/common.json';

import arSeasons from '@/locales/ar/seasons.json';
import enSeasons from '@/locales/en/seasons.json';
import esSeasons from '@/locales/es/seasons.json';

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

i18n.use(initReactI18next).init({
  resources,
  lng: 'ar',
  fallbackLng: 'ar',
  ns: ['common', 'seasons'],
  defaultNS: 'common',
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
