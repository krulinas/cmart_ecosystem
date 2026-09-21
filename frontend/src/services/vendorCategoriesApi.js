import { tt } from '../i18n';

export async function fetchVendorCategories(api) {
  const { data } = await api.get('/vendor-categories');
  return Array.isArray(data?.categories) ? data.categories : [];
}

export function categoryConflictMessage(errorCode, fallback) {
  const key = errorCode ? `booking.errors.${errorCode}` : null;
  if (key) {
    const translated = tt(key);
    if (translated && translated !== key) {
      return translated;
    }
  }
  return fallback || tt('booking.errors.fallback');
}
