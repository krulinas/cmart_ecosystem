import { VENDOR_DASHBOARD_LEGACY_HASH_REDIRECTS } from '../config/navigation';

/**
 * Resolve a `/dashboard#…` hash to a discrete vendor route path.
 * @param {string} hashOrId hash with or without leading `#`
 * @returns {string|null}
 */
export function resolveVendorDashboardLegacyHash(hashOrId) {
  const id = String(hashOrId || '').replace(/^#/, '').trim();
  if (!id) return null;
  return VENDOR_DASHBOARD_LEGACY_HASH_REDIRECTS[id] || null;
}

/** Canonical Manage → My Bookings path (checkout / booking returns). */
export const VENDOR_MANAGE_BOOKINGS_PATH = '/vendor/manage/bookings';
