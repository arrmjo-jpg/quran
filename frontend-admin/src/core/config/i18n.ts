import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import arCommon from '@/locales/ar/common.json';
import enCommon from '@/locales/en/common.json';
import esCommon from '@/locales/es/common.json';

const resources = {
  ar: { common: arCommon },
  en: { common: enCommon },
  es: { common: esCommon },
};

i18n.use(initReactI18next).init({
  resources,
  lng: 'ar',
  fallbackLng: 'ar',
  ns: ['common'],
  defaultNS: 'common',
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
