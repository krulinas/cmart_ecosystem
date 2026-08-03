/** Codebook order + display helpers for Vendors & Sales charts (mirrors Python schema). */

export const PRODUCT_CATEGORY_ORDER = [
  'makanan_minuman',
  'pakaian_fesyen',
  'elektrik_elektronik_gajet',
  'barangan_rumah_keperluan_harian',
  'buku_mainan_kanak_kanak',
  'kecantikan_penjagaan_diri',
  'perkhidmatan_promosi',
  'lain_lain',
];

export const EVENT_INFO_SOURCE_ORDER = [
  'whatsapp',
  'media_sosial',
  'pihak_penganjur',
  'rakan_kenalan',
  'lain_lain',
];

export const SALES_PURPOSE_ORDER = [
  'Pendapatan Utama',
  'Pendapatan Sampingan',
  'Hobi / Mengosongkan ruang rumah',
];

export const GROSS_SALES_BAND_ORDER = [
  'Kurang daripada RM50',
  'RM51 hingga RM150',
  'RM151 hingga RM300',
  'Melebihi RM300',
];

export const ITEMS_SOLD_BAND_ORDER = [
  'Tiada (hanya jual barang baharu/makanan)',
  'Suku (25%)',
  'Separuh (50%)',
  'Hampir habis (75%-100%)',
];

/** Shorter axis labels for crowded charts (tooltips keep full labels). */
export const SHORT_LABELS = {
  'Tiada (hanya jual barang baharu/makanan)': 'Tiada',
  'Hampir habis (75%-100%)': 'Hampir habis (75%–100%)',
  'Hobi / Mengosongkan ruang rumah': 'Hobi / Mengosongkan ruang',
};

export const CHART_COLORS = {
  brand: '#0277BD',
  brandSoft: 'rgba(2, 119, 189, 0.55)',
  brandMuted: 'rgba(2, 119, 189, 0.18)',
  selected: '#0F766E',
  selectedSoft: 'rgba(15, 118, 110, 0.75)',
  stack: ['#0277BD', '#0D9488', '#F59E0B', '#64748B'],
  zero: 'rgba(148, 163, 184, 0.45)',
};

/**
 * Merge API rows with codebook order; fill missing keys with count 0.
 * @param {Array} rows
 * @param {string[]} order
 * @param {{ includeZeros?: boolean, sortByCount?: boolean }} options
 */
export function prepareSurveyRows(rows, order, { includeZeros = false, sortByCount = false } = {}) {
  const list = Array.isArray(rows) ? rows : [];
  const byKey = new Map(list.map((r) => [String(r.key ?? r.label), r]));

  let keys = includeZeros
    ? [...order]
    : order.filter((k) => byKey.has(k) && Number(byKey.get(k)?.count || 0) > 0);

  // Append unexpected keys from API
  for (const row of list) {
    const key = String(row.key ?? row.label);
    if (!keys.includes(key) && (includeZeros || Number(row.count || 0) > 0)) {
      keys.push(key);
    }
  }

  if (!includeZeros && !order.length) {
    keys = list.map((r) => String(r.key ?? r.label));
  }

  let prepared = keys.map((key) => {
    const row = byKey.get(key) || {};
    const count = Number(row.count || 0);
    const denominator = Number(row.denominator ?? 0);
    const percent = row.percent != null
      ? Number(row.percent)
      : (denominator > 0 ? Math.round((count / denominator) * 1000) / 10 : 0);
    return {
      key,
      label: row.label || key,
      count,
      percent,
      denominator,
      display: row.display || (denominator
        ? `${count} of ${denominator} respondents (${percent}%)`
        : `${count}`),
    };
  });

  if (sortByCount) {
    prepared = [...prepared].sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));
  }

  return prepared;
}

export function metricValue(row, mode) {
  return mode === 'percent' ? Number(row.percent || 0) : Number(row.count || 0);
}

export function formatTooltip(row, mode) {
  const n = row.denominator || 0;
  const count = row.count || 0;
  const pct = row.percent != null ? row.percent : (n ? Math.round((count / n) * 1000) / 10 : 0);
  return `${count} of ${n} respondents (${pct}%)`;
}
