/**
 * Shared Post-Event Report visual specification from a frozen snapshot.
 * Presentation only — no metric formulas; never coerces missing → zero.
 * Must stay in parity with App\Support\PostEventReportVisualSpec (PHP).
 */

import { ANALYTICS_PALETTE, COMPOSITION_COLORS } from './analyticsChartPalette.js';
import { resolveCategoryChartType } from './chartLifecycle.js';

export const REPORT_VISUAL_PALETTE = Object.freeze({
  primary: ANALYTICS_PALETTE.primary,
  positive: ANALYTICS_PALETTE.positive,
  finance: ANALYTICS_PALETTE.finance,
  warning: ANALYTICS_PALETTE.warning,
  survey: ANALYTICS_PALETTE.survey,
  highlight: ANALYTICS_PALETTE.highlight,
  neutral: ANALYTICS_PALETTE.neutral,
});

const BOOKING_STATUS_ORDER = [
  'Pending_Organizer',
  'Pending_Staff',
  'Pending_Boss',
  'Needs_Revision',
  'Approved',
  'Rejected',
  'Cancelled',
  'Withdrawn',
];

function section(snapshot, key) {
  const block = snapshot?.sections?.[key];
  if (!block || block.excluded) return null;
  return block;
}

function presentCount(value) {
  if (value == null || value === '') return null;
  const n = Number(value);
  return Number.isNaN(n) ? null : n;
}

function withColors(rows, colors = COMPOSITION_COLORS) {
  return rows.map((row, idx) => ({
    ...row,
    color: row.color || colors[idx % colors.length],
  }));
}

/**
 * Canonical contract slice for JS↔PHP parity tests.
 * @param {ReturnType<typeof buildReportVisualSpec>} spec
 */
export function visualSpecContract(spec) {
  const charts = {};
  for (const [id, chart] of Object.entries(spec?.charts || {})) {
    charts[id] = {
      id: chart.id,
      type: chart.type,
      empty: Boolean(chart.empty),
      rows: (chart.rows || []).map((row) => ({
        key: String(row.key),
        label: String(row.label),
        count: Number(row.count),
        color: String(row.color || ''),
      })),
    };
  }
  return {
    palette: { ...spec.palette },
    charts,
    includePerformanceAcrossEvents: Boolean(spec.includePerformanceAcrossEvents),
  };
}

/**
 * @param {object} snapshot
 * @param {{ labelFn?: (key: string) => string }} [opts]
 */
export function buildReportVisualSpec(snapshot, opts = {}) {
  const labelFn = opts.labelFn || ((key) => String(key).replaceAll('_', ' '));
  const pipeline = section(snapshot, 'booking_pipeline');
  const payments = section(snapshot, 'payments');
  const eventPerformance = section(snapshot, 'event_performance');
  const categories = section(snapshot, 'vendor_categories');
  const feedback = section(snapshot, 'feedback');
  const survey = section(snapshot, 'vendor_survey');

  const charts = {};

  if (eventPerformance) {
    const sold = presentCount(eventPerformance.sites_sold);
    const available = presentCount(eventPerformance.available_sites);
    const rows = [];
    if (sold != null) {
      rows.push({
        key: 'sites_sold',
        label: 'Sites sold',
        count: sold,
        color: REPORT_VISUAL_PALETTE.primary,
      });
    }
    if (available != null) {
      rows.push({
        key: 'sites_remaining',
        label: 'Remaining open sites',
        count: available,
        color: REPORT_VISUAL_PALETTE.neutral,
      });
    }
    charts.site_utilisation = {
      id: 'site_utilisation',
      title: 'Site utilisation',
      subtitle: 'Sites sold versus remaining open booking sites',
      type: 'doughnut',
      rows: withColors(rows),
      centerValue: eventPerformance.site_utilisation_percent != null
        ? `${eventPerformance.site_utilisation_percent}%`
        : null,
      centerLabel: 'Utilised',
      empty: rows.length === 0,
    };
  }

  // Vendor categories — adaptive doughnut (2–5) / ranked bar (6+) / compact (0–1)
  {
    const dist = Array.isArray(categories?.distribution) ? categories.distribution : [];
    const rows = dist
      .map((row) => {
        const count = presentCount(row.unique_vendors ?? row.count);
        if (count == null || count <= 0) return null;
        return {
          key: String(row.key || row.label || count),
          label: String(row.label || labelFn(row.key)),
          count,
        };
      })
      .filter(Boolean);
    const type = resolveCategoryChartType(rows.length);
    charts.vendor_categories = {
      id: 'vendor_categories',
      title: 'Product categories — System Data',
      subtitle: 'Unique participating vendors from approved bookings',
      type,
      rows: withColors(rows),
      empty: rows.length === 0,
    };
  }

  // Collected vs outstanding — horizontal stacked bar (never doughnut)
  if (payments) {
    const collected = presentCount(
      payments.collected_revenue ?? payments.collected_booth_fees ?? payments.collected,
    );
    const outstanding = presentCount(
      payments.outstanding_invoice_balance ?? payments.outstanding ?? payments.unpaid_approved,
    );
    const invoiceCount = presentCount(payments.invoice_count ?? payments.invoice_count_approved);
    const rows = [];
    if (collected != null) {
      rows.push({
        key: 'collected',
        label: 'Collected',
        count: collected,
        color: REPORT_VISUAL_PALETTE.positive,
      });
    }
    if (invoiceCount != null && invoiceCount > 0 && outstanding != null) {
      rows.push({
        key: 'outstanding',
        label: 'Outstanding',
        count: outstanding,
        color: REPORT_VISUAL_PALETTE.warning,
      });
    }
    charts.revenue_collection = {
      id: 'revenue_collection',
      title: 'Revenue collection',
      subtitle: 'Collected versus outstanding invoice balance',
      type: 'stacked_bar',
      rows: withColors(rows),
      empty: rows.length === 0 || !rows.some((r) => r.count > 0),
    };
  }

  if (pipeline) {
    const by = pipeline.by_approval_status || {};
    const keys = [
      ...BOOKING_STATUS_ORDER.filter((k) => Object.prototype.hasOwnProperty.call(by, k)),
      ...Object.keys(by).filter((k) => !BOOKING_STATUS_ORDER.includes(k)),
    ];
    const rows = keys
      .map((key) => {
        const count = presentCount(by[key]);
        if (count == null || count <= 0) return null;
        return { key, label: labelFn(key), count };
      })
      .filter(Boolean);
    charts.booking_status = {
      id: 'booking_status',
      title: 'Booking status mix',
      subtitle: 'Mutually exclusive approval statuses for this event',
      type: 'doughnut',
      rows: withColors(rows),
      empty: rows.length === 0,
    };
  }

  if (feedback && Number(feedback.response_count) > 0) {
    const ratingDist = feedback.rating_distribution || {};
    const rows = [1, 2, 3, 4, 5]
      .map((star) => {
        const count = presentCount(ratingDist[star] ?? ratingDist[String(star)]);
        if (count == null || count <= 0) return null;
        return {
          key: `rating_${star}`,
          label: `${star}★`,
          count,
        };
      })
      .filter(Boolean);
    charts.feedback_ratings = {
      id: 'feedback_ratings',
      title: 'Community feedback rating distribution',
      subtitle: 'In-app Feedback for this event',
      type: 'bar',
      rows: withColors(rows, [
        REPORT_VISUAL_PALETTE.warning,
        REPORT_VISUAL_PALETTE.highlight,
        REPORT_VISUAL_PALETTE.neutral,
        REPORT_VISUAL_PALETTE.primary,
        REPORT_VISUAL_PALETTE.positive,
      ]),
      empty: rows.length === 0,
    };
  }

  const surveyCharts = [];
  if (survey && survey.available && !survey.excluded && survey.distributions) {
    for (const [key, block] of Object.entries(survey.distributions)) {
      if (!block || block.excluded) continue;
      const rawRows = Array.isArray(block.rows) ? block.rows : [];
      const rows = rawRows
        .map((row) => {
          const count = presentCount(row.count);
          if (count == null) return null;
          return {
            key: row.key || row.label,
            label: row.label || labelFn(row.key),
            count,
            percent: row.percent ?? null,
          };
        })
        .filter(Boolean);
      surveyCharts.push({
        id: `survey_${key}`,
        title: key,
        type: 'bar',
        rows: withColors(rows),
        empty: rows.length === 0,
        multiSelect: Boolean(block.multi_select),
        denominatorNote: block.denominator_note || null,
        message: block.message || null,
      });
    }
  }

  return {
    palette: REPORT_VISUAL_PALETTE,
    charts,
    surveyCharts,
    includePerformanceAcrossEvents: false,
    performanceAcrossEventsNote:
      'Performance Across Events is omitted from the official report because cross-event benchmark results are not frozen into the report snapshot.',
  };
}
