/**
 * Pure display helpers for Organizer Analytics Hub (Part 04.1).
 * Keep business formulas in Laravel; this only formats / selects payload fields.
 */

/** Unique participating vendors — never fall back to approved booking count. */
export function resolveUniqueApprovedVendors(eventPerformance) {
  const unique = eventPerformance?.unique_approved_vendors;
  if (unique == null || unique === '') return null;
  const n = Number(unique);
  return Number.isNaN(n) ? null : n;
}

/**
 * Null/empty/invalid → null (caller shows — / unavailable).
 * Genuine numeric zero → formatted "0.00".
 */
export function formatMoneyAmount(value, formatLocaleNumber) {
  if (value == null || value === '') return null;
  const n = Number(value);
  if (Number.isNaN(n)) return null;
  return formatLocaleNumber(n, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Missing → "—"; zero → "RM 0.00". Never "RM —". */
export function displayMoney(value, formatLocaleNumber) {
  const amount = formatMoneyAmount(value, formatLocaleNumber);
  return amount == null ? '—' : `RM ${amount}`;
}

/**
 * Localized booking / site operational status labels via status.* catalogue.
 * Unknown keys fall back to humanized text (never raw status.* keys).
 */
export function operationalStatusLabel(key, t) {
  const raw = String(key ?? '').trim();
  if (!raw) return t('common.unknown');

  const direct = t(`status.${raw}`);
  if (direct && direct !== `status.${raw}` && !String(direct).startsWith('status.')) {
    return direct;
  }

  const spaced = raw.replace(/_/g, ' ');
  if (spaced !== raw) {
    const spacedLabel = t(`status.${spaced}`);
    if (
      spacedLabel
      && spacedLabel !== `status.${spaced}`
      && !String(spacedLabel).startsWith('status.')
    ) {
      return spacedLabel;
    }
  }

  return raw.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
