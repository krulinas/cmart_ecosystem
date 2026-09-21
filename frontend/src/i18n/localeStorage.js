/**
 * Locale persistence and document language helpers.
 * Default: Bahasa Melayu (ms). Fallback: English (en).
 */
export const LOCALE_STORAGE_KEY = 'cmart_ui_locale';
export const SUPPORTED_LOCALES = ['ms', 'en'];
export const DEFAULT_LOCALE = 'ms';
export const FALLBACK_LOCALE = 'en';

export const LOCALE_BCP47 = {
  ms: 'ms-MY',
  en: 'en-MY',
};

export function normalizeLocale(value) {
  const raw = String(value || '').trim().toLowerCase();
  if (raw === 'ms' || raw.startsWith('ms-')) return 'ms';
  if (raw === 'en' || raw.startsWith('en-')) return 'en';
  return null;
}

export function readStoredLocale() {
  try {
    return normalizeLocale(localStorage.getItem(LOCALE_STORAGE_KEY));
  } catch {
    return null;
  }
}

export function persistLocale(locale) {
  const normalized = normalizeLocale(locale) || DEFAULT_LOCALE;
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, normalized);
  } catch {
    // Ignore quota / private-mode failures; in-memory locale still applies.
  }
  return normalized;
}

export function resolveInitialLocale() {
  return readStoredLocale() || DEFAULT_LOCALE;
}

export function applyDocumentLang(locale) {
  const normalized = normalizeLocale(locale) || DEFAULT_LOCALE;
  if (typeof document !== 'undefined' && document.documentElement) {
    document.documentElement.setAttribute('lang', normalized);
  }
  return normalized;
}

export function bcp47ForLocale(locale) {
  const normalized = normalizeLocale(locale) || DEFAULT_LOCALE;
  return LOCALE_BCP47[normalized] || LOCALE_BCP47[DEFAULT_LOCALE];
}
