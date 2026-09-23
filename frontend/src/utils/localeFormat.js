/**
 * Locale-aware display formatters (ms-MY / en-MY).
 * Do not use for stored values or API payloads.
 */
import { getAppLocale } from '../i18n';
import { bcp47ForLocale } from '../i18n/localeStorage';

const MY_TZ = 'Asia/Kuala_Lumpur';

export function localeTag(locale) {
  return bcp47ForLocale(locale ?? getAppLocale());
}

export function formatLocaleDate(value, options = {}, locale) {
  if (value == null || value === '') return '';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString(localeTag(locale), {
    timeZone: MY_TZ,
    ...options,
  });
}

export function formatLocaleDateTime(value, options = {}, locale) {
  if (value == null || value === '') return '';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString(localeTag(locale), {
    timeZone: MY_TZ,
    ...options,
  });
}

export function formatLocaleNumber(value, options = {}, locale) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) return '0';
  return new Intl.NumberFormat(localeTag(locale), options).format(n);
}

/** Prefer RM prefix to match existing UI design. */
export function formatLocaleMoneyRm(value, locale) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) return 'RM 0.00';
  return `RM ${new Intl.NumberFormat(localeTag(locale), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n)}`;
}

export function formatLocaleCurrencyMyr(value, locale) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) {
    return new Intl.NumberFormat(localeTag(locale), {
      style: 'currency',
      currency: 'MYR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(0);
  }
  return new Intl.NumberFormat(localeTag(locale), {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}
