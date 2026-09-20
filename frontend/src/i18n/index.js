import { createI18n } from 'vue-i18n';
import en from './locales/en';
import ms from './locales/ms';

export const DEFAULT_LOCALE = 'ms';
export const SUPPORTED_LOCALES = ['ms', 'en'];
export const LOCALE_STORAGE_KEY = 'cmart_ui_locale';

const storedLocale = typeof window !== 'undefined'
  ? window.localStorage.getItem(LOCALE_STORAGE_KEY)
  : null;

const initialLocale = SUPPORTED_LOCALES.includes(storedLocale)
  ? storedLocale
  : DEFAULT_LOCALE;

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
  fallbackLocale: 'en',
  messages: { en, ms },
});

export function setAppLocale(locale) {
  const nextLocale = SUPPORTED_LOCALES.includes(locale) ? locale : DEFAULT_LOCALE;
  i18n.global.locale.value = nextLocale;

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, nextLocale);
  }

  if (typeof document !== 'undefined') {
    document.documentElement.lang = nextLocale;
  }
}

setAppLocale(initialLocale);
