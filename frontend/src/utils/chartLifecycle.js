/**
 * Shared Chart.js lifecycle helpers for Organizer Analytics charts.
 */

/**
 * @param {import('chart.js').Chart | null | undefined} chart
 */
export function destroyChartInstance(chart) {
  if (chart && typeof chart.destroy === 'function') {
    chart.destroy();
  }
  return null;
}

/**
 * Filter segment rows: keep numeric counts; exclude null/undefined metrics.
 * Zeros are allowed when present; missing values stay out.
 *
 * @param {Array<{ label?: string, key?: string, value?: number|null, count?: number|null }>} rows
 * @param {'count'|'value'} field
 */
export function presentNumericRows(rows, field = 'count') {
  const list = Array.isArray(rows) ? rows : [];
  return list
    .map((row) => {
      const raw = field === 'value' ? row.value : (row.count ?? row.value);
      if (raw == null || raw === '') return null;
      const n = Number(raw);
      if (Number.isNaN(n)) return null;
      return {
        key: String(row.key ?? row.label ?? ''),
        label: String(row.label ?? row.key ?? ''),
        count: n,
        percent: row.percent != null && row.percent !== '' ? Number(row.percent) : null,
        meta: row,
      };
    })
    .filter(Boolean);
}

/**
 * Doughnut vs ranked-bar decision for mutually exclusive categories.
 * @param {number} nonZeroCount
 */
export function shouldUseDoughnutForCategories(nonZeroCount) {
  return nonZeroCount >= 2 && nonZeroCount <= 5;
}

/**
 * Adaptive category chart type used by Analytics Hub, Preview and PDF.
 * @param {number} validCount categories with present numeric counts (> 0)
 * @returns {'doughnut'|'bar'|'compact'}
 */
export function resolveCategoryChartType(validCount) {
  const n = Number(validCount) || 0;
  if (n >= 2 && n <= 5) return 'doughnut';
  if (n >= 6) return 'bar';
  return 'compact';
}
