import { createI18n } from 'vue-i18n';
import ms from './locales/ms';
import en from './locales/en';
import {
  DEFAULT_LOCALE,
  FALLBACK_LOCALE,
  applyDocumentLang,
  persistLocale,
  resolveInitialLocale,
  normalizeLocale,
} from './localeStorage';

const initialLocale = resolveInitialLocale();
applyDocumentLang(initialLocale);

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: initialLocale,
  fallbackLocale: FALLBACK_LOCALE,
  messages: {
    ms,
    en,
  },
  missingWarn: false,
  fallbackWarn: false,
});

export function setAppLocale(locale) {
  const next = normalizeLocale(locale) || DEFAULT_LOCALE;
  i18n.global.locale.value = next;
  persistLocale(next);
  applyDocumentLang(next);
  return next;
}

export function getAppLocale() {
  return normalizeLocale(i18n.global.locale.value) || DEFAULT_LOCALE;
}

/** Translate outside Vue setup (utils). Prefer useI18n().t inside components. */
export function tt(key, values) {
  try {
    return i18n.global.t(key, values);
  } catch {
    return String(key);
  }
}

export function statusLabel(internalValue, t = tt) {
  if (internalValue == null || internalValue === '') return '';
  const key = `status.${internalValue}`;
  const translated = t(key);
  return translated === key ? String(internalValue) : translated;
}

export default i18n;
