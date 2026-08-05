<template>
  <article class="pes-report" data-testid="post-event-summary-view">
    <!-- 1. Cover -->
    <header class="pes-cover">
      <div class="pes-cover__brand">
        <img src="/cmart_logo.png" alt="CMart" class="pes-cover__logo" @error="logoFailed = true" v-show="!logoFailed" />
        <span v-if="logoFailed" class="pes-cover__logo-fallback">CMart</span>
      </div>
      <p class="pes-eyebrow">Post-Event Report</p>
      <h1 class="pes-cover__title">{{ eventTitle }}</h1>
      <p class="pes-cover__date">{{ dateRange }}</p>
      <dl class="pes-cover__meta">
        <div><dt>Venue</dt><dd>{{ venue }}</dd></div>
        <div><dt>Prepared by</dt><dd>Carboot Organizer</dd></div>
        <div><dt>Prepared for</dt><dd>CMart</dd></div>
        <div><dt>Report version</dt><dd>Version {{ report.version }}</dd></div>
        <div v-if="publishedDisplay"><dt>Publication date</dt><dd>{{ publishedDisplay }}</dd></div>
      </dl>
      <span v-if="coverStatus" class="pes-badge" :class="coverStatus === 'Provisional' ? 'pes-badge--amber' : 'pes-badge--green'">
        {{ coverStatus }}
      </span>
    </header>

    <!-- 2. Executive Summary -->
    <section class="pes-section">
      <h2>1. Executive Summary</h2>
      <p v-if="coverStatus === 'Provisional'" class="pes-warn">
        This report is Provisional. Figures reflect the available snapshot and may change if a later version is published.
      </p>
      <div class="pes-kpi-grid">
        <div v-for="kpi in executiveKpis" :key="kpi.label" class="pes-kpi">
          <div class="pes-kpi__label">{{ kpi.label }}</div>
          <div class="pes-kpi__value">{{ kpi.value }}</div>
        </div>
      </div>
      <p class="pes-summary">{{ executiveNarrative }}</p>
    </section>

    <!-- 3. Event and Participation -->
    <section class="pes-section">
      <h2>2. Event and Participation</h2>
      <div class="pes-panel">
        <dl class="pes-kv">
          <div><dt>Event</dt><dd>{{ eventTitle }}</dd></div>
          <div><dt>Date &amp; time</dt><dd>{{ dateRange }}</dd></div>
          <div><dt>Venue</dt><dd>{{ venue }}</dd></div>
        </dl>
      </div>

      <template v-if="pipeline">
        <h3>Applications and pipeline</h3>
        <dl class="pes-kv">
          <div><dt>Applications</dt><dd>{{ displayMetric(pipeline.total_bookings) }}</dd></div>
          <div><dt>Unique applicants</dt><dd>{{ displayMetric(pipeline.unique_applicants) }}</dd></div>
          <div><dt>Approved bookings</dt><dd>{{ displayMetric(firstDefined(pipeline.approved_count, pipeline.by_approval_status?.Approved)) }}</dd></div>
          <div><dt>Approved vendors</dt><dd>{{ displayMetric(pipeline.approved_unique_vendors) }}</dd></div>
        </dl>
        <div v-if="statusBars.length" class="pes-bars">
          <div v-for="row in statusBars" :key="row.label" class="pes-bar">
            <div class="pes-bar__label"><span>{{ row.label }}</span><strong>{{ row.count }}</strong></div>
            <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
          </div>
        </div>
        <p class="pes-note">
          Only statuses with recorded applications are shown. Application counts and unique-vendor counts are separate.
          Approved bookings are not verified attendance.
        </p>
      </template>

      <h3>Verified check-ins</h3>
      <template v-if="attendanceRecorded">
        <dl class="pes-kv">
          <div><dt>Verified check-ins</dt><dd>{{ attendance.verified_check_in_count }}</dd></div>
        </dl>
        <p class="pes-note">A single check-in timestamp does not prove complete multi-day attendance.</p>
      </template>
      <p v-else class="pes-muted" data-testid="attendance-not-recorded">
        {{ attendance?.message || 'Attendance verification was not recorded for this event.' }}
      </p>

      <template v-if="utilisationSection">
        <h3>Site-day utilisation</h3>
        <template v-if="utilisationSection.available">
          <dl class="pes-kv">
            <div><dt>Available active site-days</dt><dd>{{ utilisationSection.available_active_site_days }}</dd></div>
            <div><dt>Occupied site-days</dt><dd>{{ utilisationSection.occupied_site_days }}</dd></div>
            <div><dt>Site-day utilisation</dt><dd>{{ utilisationSection.utilisation_percent }}%</dd></div>
          </dl>
          <div class="pes-bar">
            <div class="pes-bar__track">
              <div
                class="pes-bar__fill pes-bar__fill--green"
                :style="{ width: `${Math.max(2, Math.min(100, Number(utilisationSection.utilisation_percent) || 0))}%` }"
              ></div>
            </div>
          </div>
          <p class="pes-note">
            Site-day utilisation = occupied active site-days ÷ available active site-days × 100.
            Unavailable sites are excluded. This is not unique physical-booth occupancy.
          </p>
        </template>
        <p v-else class="pes-muted" data-testid="utilisation-unavailable">
          {{ utilisationSection.message || 'Not available for this event' }}
        </p>
      </template>

      <template v-if="categories.length">
        <h3>Approved vendor categories</h3>
        <div class="pes-bars">
          <div v-for="row in categoryBars" :key="row.label" class="pes-bar">
            <div class="pes-bar__label"><span>{{ row.label }}</span><strong>{{ row.count }}</strong></div>
            <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
          </div>
        </div>
      </template>
    </section>

    <!-- 4. Financial Summary -->
    <section v-if="payments" class="pes-section">
      <h2>3. Financial Summary</h2>
      <p class="pes-note">Organizer booth-fee invoices only. Vendor-reported survey sales are not organizer revenue.</p>
      <dl class="pes-kv">
        <div><dt>Expected booth fees</dt><dd>{{ moneyOrMissing(paymentExpected) }}</dd></div>
        <div><dt>Collected booth fees</dt><dd class="pes-pos">{{ moneyOrMissing(paymentCollected) }}</dd></div>
        <div><dt>Unpaid amount</dt><dd class="pes-amber">{{ moneyOrMissing(paymentUnpaid) }}</dd></div>
        <div v-if="paymentPending != null"><dt>Pending verification</dt><dd>{{ moneyOrMissing(paymentPending) }}</dd></div>
        <div v-if="paymentRefunded != null"><dt>Refunded</dt><dd>{{ moneyOrMissing(paymentRefunded) }}</dd></div>
        <div v-if="collectionRateValue != null"><dt>Collection rate</dt><dd>{{ collectionRateValue }}%</dd></div>
        <div v-if="withoutInvoice != null">
          <dt>Approved bookings without invoices</dt><dd>{{ withoutInvoice }}</dd>
        </div>
      </dl>
      <p v-if="paidWithdrawalDisclosure" class="pes-note" data-testid="paid-withdrawal-disclosure">
        {{ paidWithdrawalDisclosure }}
      </p>
      <p v-if="payments.potentially_incomplete" class="pes-warn">
        Financial summary may be incomplete because one or more approved bookings have no invoice.
        Missing invoices are not treated as RM 0.00 due.
      </p>
    </section>

    <!-- 5. Vendor and Sales Insights -->
    <section v-if="showVendorInsights" class="pes-section" data-testid="survey-section">
      <h2>4. Vendor and Sales Insights</h2>
      <template v-if="surveyAvailable">
        <p class="pes-note">
          {{ surveySection.base_display || `n = ${surveySection.respondent_count} responses` }}.
          Categorical survey aggregates only; exact total vendor revenue is not calculated.
        </p>
        <div v-for="block in surveyBlocks" :key="block.key" class="pes-dist">
          <h3>{{ block.title }}</h3>
          <p v-if="block.message" class="pes-muted">{{ block.message }}</p>
          <template v-else>
            <p class="pes-note">
              {{ block.base }}
              <template v-if="block.denominatorNote"> · {{ block.denominatorNote }}</template>
              <template v-if="block.multiSelect"> · Multiple responses allowed; percentages may exceed 100%.</template>
            </p>
            <div class="pes-bars">
              <div v-for="row in block.rows" :key="`${block.key}-${row.key}`" class="pes-bar">
                <div class="pes-bar__label">
                  <span>{{ row.label }}</span>
                  <strong>{{ row.count }}<template v-if="row.percent != null"> · {{ row.percent }}%</template></strong>
                </div>
                <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
              </div>
            </div>
          </template>
        </div>
      </template>
      <p v-else-if="categories.length" class="pes-muted">
        Survey responses were not available for this event. Approved vendor categories are shown in Participation.
      </p>
    </section>

    <!-- 6. Environmental and Social -->
    <section v-if="environmentalAvailable" class="pes-section">
      <h2>5. Environmental and Social Insights</h2>
      <p class="pes-note">
        <strong>Vendor-reported survey indicators.</strong>
        These indicators are based on vendor responses and are not direct measurements of waste, carbon emissions or total items sold.
      </p>
      <dl class="pes-kv">
        <div>
          <dt>Vendors reporting reused / preloved goods</dt>
          <dd>{{ environmentalSection.vendors_reporting_reused_goods ?? 0 }}</dd>
        </div>
        <div><dt>Plans to donate</dt><dd>{{ environmentalSection.plans_to_donate ?? 0 }}</dd></div>
        <div><dt>Plans to recycle</dt><dd>{{ environmentalSection.plans_to_recycle ?? 0 }}</dd></div>
        <div><dt>Plans to relist / store</dt><dd>{{ environmentalSection.plans_to_relist_or_store ?? 0 }}</dd></div>
        <div><dt>Plans to dispose</dt><dd>{{ environmentalSection.plans_to_dispose ?? 0 }}</dd></div>
      </dl>
      <template v-if="usedStockBars.length">
        <h3>Used-stock sold bands</h3>
        <div class="pes-chips">
          <span v-for="row in usedStockBars" :key="row.label" class="pes-chip">{{ row.label }}: {{ row.count }}</span>
        </div>
      </template>
      <template v-if="supportEffectBars.length">
        <h3>Perceived effect of supporting activities</h3>
        <div class="pes-chips">
          <span v-for="row in supportEffectBars" :key="row.label" class="pes-chip">{{ row.label }}: {{ row.count }}</span>
        </div>
      </template>
    </section>

    <!-- 7. Organizer Assessment -->
    <section v-if="report.organizer_observations || report.organizer_recommendations" class="pes-section">
      <h2>6. Organizer Assessment</h2>
      <template v-if="report.organizer_observations">
        <h3>Organizer observations</h3>
        <div class="pes-narrative">{{ report.organizer_observations }}</div>
      </template>
      <template v-if="report.organizer_recommendations">
        <h3>Recommendations</h3>
        <div class="pes-narrative">{{ report.organizer_recommendations }}</div>
      </template>
    </section>

    <!-- 8. Methodology -->
    <section class="pes-section">
      <h2>7. Methodology and Data Notes</h2>
      <dl class="pes-kv pes-kv--method">
        <div><dt>Report scope</dt><dd>This report covers one carboot event only.</dd></div>
        <div>
          <dt>Report version</dt>
          <dd>Version {{ report.version }}<template v-if="coverStatus"> ({{ coverStatus }})</template></dd>
        </div>
        <div v-if="dataCutOff"><dt>Data cut-off</dt><dd>{{ dataCutOff }}</dd></div>
        <div>
          <dt>Applications vs unique vendors</dt>
          <dd>Application counts and unique applicant/vendor counts are separate.</dd>
        </div>
        <div>
          <dt>Approved bookings vs attendance</dt>
          <dd>Approved bookings are not labelled as attendance unless verified check-ins are recorded.</dd>
        </div>
        <div>
          <dt>Site-day utilisation</dt>
          <dd>Occupied active site-days ÷ available active site-days × 100.</dd>
        </div>
        <div v-if="surveyAvailable">
          <dt>Survey response base</dt>
          <dd>{{ surveySection.base_display || `n = ${surveySection.respondent_count} responses` }}</dd>
        </div>
        <div v-if="surveyAvailable">
          <dt>Multi-select questions</dt>
          <dd>Multiple responses allowed; percentages may exceed 100%.</dd>
        </div>
        <div>
          <dt>Financial inclusion</dt>
          <dd>
            Collected booth fees include paid approved invoices and paid withdrawn bookings under the
            non-refundable withdrawal policy. Pending verification and refunds are shown separately when present.
          </dd>
        </div>
        <div>
          <dt>Missing data</dt>
          <dd>Missing or unavailable metrics are omitted or shown as Not recorded / Not available — never invented as zero.</dd>
        </div>
        <div v-if="dataQualityWarnings.length">
          <dt>Data-quality warnings</dt>
          <dd>{{ dataQualityWarnings.join('; ') }}</dd>
        </div>
        <div>
          <dt>Provisional / Final</dt>
          <dd>Provisional means the snapshot may still change. Final means the published snapshot for this version is frozen.</dd>
        </div>
      </dl>
    </section>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
  collectionRate,
  formatReportMoney,
  reportDistributionTitle,
  reportOptionLabel,
} from '../../utils/postEventReportPresentation.js';

const props = defineProps({
  report: { type: Object, required: true },
});

const NOT_RECORDED = 'Not recorded';
const NOT_AVAILABLE = 'Not available for this event';
const logoFailed = ref(false);

const snapshot = computed(() => props.report?.snapshot || {});
const eventTitle = computed(
  () => props.report.event_title_snapshot || snapshot.value?.event?.title || 'Carboot Event',
);
const venue = computed(() => snapshot.value?.event?.venue || snapshot.value?.venue || 'CMart');

const pipeline = computed(() => {
  const section = snapshot.value?.sections?.booking_pipeline;
  if (!section || section.excluded) return null;
  return section;
});
const payments = computed(() => {
  const section = snapshot.value?.sections?.payments;
  if (!section || section.excluded) return null;
  return section;
});
const utilisationSection = computed(() => {
  const section = snapshot.value?.sections?.site_day_utilisation;
  if (!section || section.excluded) return null;
  return section;
});
const attendance = computed(() => snapshot.value?.sections?.attendance || null);
const surveySection = computed(() => {
  const section = snapshot.value?.sections?.vendor_survey;
  if (!section || section.excluded) return null;
  return section;
});
const environmentalSection = computed(() => snapshot.value?.sections?.environmental_social || null);

const attendanceRecorded = computed(
  () => Boolean(attendance.value?.recorded) && attendance.value?.verified_check_in_count != null,
);
const surveyAvailable = computed(() => Boolean(surveySection.value?.available));
const environmentalAvailable = computed(() => Boolean(environmentalSection.value?.available));

const coverStatus = computed(() => {
  if (props.report?.cover_status) return props.report.cover_status;
  if (snapshot.value?.provisional) return 'Provisional';
  if (props.report?.status === 'published' || props.report?.status === 'superseded') return 'Final';
  return null;
});

const dateRange = computed(() => {
  const display = props.report?.event_date_range_display || snapshot.value?.event?.date_range_display;
  if (display) return display;
  const start = props.report.event_starts_at_snapshot || snapshot.value?.event?.starts_at;
  const end = props.report.event_ends_at_snapshot || snapshot.value?.event?.ends_at;
  if (!start && !end) return 'Not recorded';
  return `${formatEnglishDate(start)} – ${formatEnglishDate(end)}`;
});

const publishedDisplay = computed(
  () => props.report?.published_at_display || formatEnglishDate(props.report?.published_at),
);

const paymentExpected = computed(() => firstDefined(payments.value?.expected_booth_fees, payments.value?.expected));
const paymentCollected = computed(() => firstDefined(payments.value?.collected_booth_fees, payments.value?.collected));
const paymentUnpaid = computed(() => firstDefined(payments.value?.unpaid_approved, payments.value?.outstanding));
const paymentPending = computed(() => payments.value?.pending_verification_approved ?? null);
const paymentRefunded = computed(() => payments.value?.refunded_approved ?? null);
const withoutInvoice = computed(() => payments.value?.approved_bookings_without_invoice ?? null);
const paidWithdrawalDisclosure = computed(() => payments.value?.paid_withdrawals?.disclosure || null);
const collectionRateValue = computed(() => collectionRate(paymentCollected.value, paymentExpected.value));

const categories = computed(() => {
  const dist = snapshot.value?.sections?.vendor_categories?.distribution;
  if (!dist) return [];
  if (Array.isArray(dist)) {
    return dist.map((row) => ({
      label: row.label || row.category || 'Unspecified',
      count: Number(row.count) || 0,
    }));
  }
  return Object.entries(dist).map(([label, count]) => ({ label, count: Number(count) || 0 }));
});

const showVendorInsights = computed(() => surveyAvailable.value || categories.value.length > 0);

const statusBars = computed(() => {
  const p = pipeline.value;
  if (!p) return [];
  const by = p.by_approval_status || {};
  const entries = [
    ['Pending', firstDefined(p.pending_count, sumStatus(by, ['Pending_Organizer', 'Pending_Staff', 'Pending_Boss']))],
    ['Needs revision', firstDefined(p.needs_revision_count, by.Needs_Revision)],
    ['Approved', firstDefined(p.approved_count, by.Approved)],
    ['Rejected', firstDefined(p.rejected_count, by.Rejected)],
    ['Cancelled', firstDefined(p.cancelled_count, by.Cancelled)],
    ['Withdrawn', firstDefined(p.withdrawn_count, by.Withdrawn)],
  ]
    .map(([label, count]) => ({ label, count: Number(count) || 0 }))
    .filter((row) => row.count > 0);
  const max = Math.max(...entries.map((row) => row.count), 1);
  return entries.map((row) => ({
    ...row,
    width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
  }));
});

const categoryBars = computed(() => {
  const max = Math.max(...categories.value.map((row) => row.count), 1);
  return categories.value.map((row) => ({
    ...row,
    width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
  }));
});

const surveyBlocks = computed(() => {
  const distributions = surveySection.value?.distributions || {};
  return Object.entries(distributions)
    .filter(([, dist]) => (dist?.rows && dist.rows.length) || dist?.message)
    .map(([key, dist]) => {
      const rows = (dist.rows || []).map((row) => ({
        key: row.key || row.label,
        label: reportOptionLabel(row.label || row.key),
        count: Number(row.count) || 0,
        percent: row.percent ?? null,
      }));
      const max = Math.max(...rows.map((row) => row.count), 1);
      return {
        key,
        title: reportDistributionTitle(key),
        base: dist.base_display || '',
        denominatorNote: dist.denominator_note || null,
        multiSelect: Boolean(dist.multi_select),
        message: !rows.length ? dist.message || null : null,
        rows: rows.map((row) => ({
          ...row,
          width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
        })),
      };
    });
});

const usedStockBars = computed(() =>
  (environmentalSection.value?.used_stock_sales_bands?.rows || []).map((row) => ({
    label: reportOptionLabel(row.label || row.key),
    count: Number(row.count) || 0,
  })),
);

const supportEffectBars = computed(() =>
  (environmentalSection.value?.supporting_activity_effect?.rows || []).map((row) => ({
    label: reportOptionLabel(row.label || row.key),
    count: Number(row.count) || 0,
  })),
);

const executiveKpis = computed(() => {
  const cards = [];
  if (pipeline.value?.total_bookings != null) {
    cards.push({ label: 'Applications', value: pipeline.value.total_bookings });
  }
  if (pipeline.value) {
    const approved = firstDefined(pipeline.value.approved_count, pipeline.value.by_approval_status?.Approved);
    if (approved != null) cards.push({ label: 'Approved bookings', value: approved });
    if (pipeline.value.approved_unique_vendors != null) {
      cards.push({ label: 'Approved vendors', value: pipeline.value.approved_unique_vendors });
    }
  }
  if (attendanceRecorded.value) {
    cards.push({ label: 'Verified check-ins', value: attendance.value.verified_check_in_count });
  }
  if (utilisationSection.value?.available && utilisationSection.value.utilisation_percent != null) {
    cards.push({ label: 'Site-day utilisation', value: `${utilisationSection.value.utilisation_percent}%` });
  }
  if (paymentCollected.value != null) {
    cards.push({ label: 'Collected booth fees', value: formatReportMoney(paymentCollected.value) });
  }
  if (surveyAvailable.value && surveySection.value.respondent_count != null) {
    cards.push({ label: 'Survey respondents', value: surveySection.value.respondent_count });
  }
  return cards;
});

const executiveNarrative = computed(() => {
  const bits = [];
  if (pipeline.value?.total_bookings != null) bits.push(`${pipeline.value.total_bookings} applications were recorded for this event`);
  const approved = pipeline.value
    ? firstDefined(pipeline.value.approved_count, pipeline.value.by_approval_status?.Approved)
    : null;
  if (approved != null) bits.push(`${approved} approved bookings`);
  if (pipeline.value?.approved_unique_vendors != null) {
    bits.push(`${pipeline.value.approved_unique_vendors} approved unique vendors`);
  }
  if (attendanceRecorded.value) bits.push(`${attendance.value.verified_check_in_count} verified check-ins`);
  if (utilisationSection.value?.available && utilisationSection.value.utilisation_percent != null) {
    bits.push(`site-day utilisation of ${utilisationSection.value.utilisation_percent}%`);
  }
  if (paymentCollected.value != null) bits.push(`collected booth fees of ${formatReportMoney(paymentCollected.value)}`);
  if (surveyAvailable.value && surveySection.value.respondent_count != null) {
    bits.push(`${surveySection.value.respondent_count} survey responses`);
  }
  if (!bits.length) {
    return 'This report summarises the available snapshot for the selected event. Some operational or survey indicators were not recorded.';
  }
  return `Based on the frozen event snapshot, this report covers ${bits.join(', ')}. Figures reflect recorded system and survey data only and do not imply an overall success judgement.`;
});

const dataCutOff = computed(
  () => snapshot.value?.methodology?.data_cut_off || snapshot.value?.generated_at_display || null,
);

const dataQualityWarnings = computed(() => {
  const warnings =
    snapshot.value?.methodology?.data_quality_warnings || snapshot.value?.data_quality_warnings || [];
  return Array.isArray(warnings) ? warnings.filter(Boolean).map(String) : [];
});

function firstDefined(...values) {
  for (const value of values) {
    if (value !== undefined && value !== null) return value;
  }
  return null;
}

function sumStatus(by, keys) {
  if (!by) return null;
  let total = 0;
  let found = false;
  keys.forEach((key) => {
    if (by[key] != null) {
      total += Number(by[key]);
      found = true;
    }
  });
  return found ? total : null;
}

function displayMetric(value) {
  if (value === undefined || value === null) return NOT_RECORDED;
  return value;
}

function moneyOrMissing(value) {
  const formatted = formatReportMoney(value);
  return formatted ?? NOT_AVAILABLE;
}

function formatEnglishDate(value) {
  if (!value) return '—';
  try {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return NOT_RECORDED;
    return new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Kuala_Lumpur',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    }).format(date);
  } catch {
    return NOT_RECORDED;
  }
}
</script>

<style scoped>
.pes-report {
  --pes-navy: #014a7a;
  --pes-blue: #0277bd;
  --pes-sky: #e1f5fe;
  --pes-ink: #0f172a;
  --pes-muted: #64748b;
  --pes-line: #e2e8f0;
  --pes-green: #047857;
  --pes-amber: #b45309;
  color: var(--pes-ink);
  font-size: 0.95rem;
  line-height: 1.5;
}

.pes-cover {
  border: 1px solid var(--pes-line);
  background: linear-gradient(180deg, #f8fbff 0%, #ffffff 55%);
  padding: 1.75rem 1.5rem 1.5rem;
  margin-bottom: 1.75rem;
}

.pes-cover__logo {
  height: 2.5rem;
  width: auto;
  margin-bottom: 1.25rem;
}

.pes-cover__logo-fallback {
  display: inline-block;
  margin-bottom: 1.25rem;
  background: var(--pes-blue);
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 0.55rem 0.85rem;
}

.pes-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pes-blue);
}

.pes-cover__title {
  margin: 0.6rem 0 0.35rem;
  font-size: 1.75rem;
  line-height: 1.2;
  color: var(--pes-navy);
  font-weight: 800;
}

.pes-cover__date {
  margin: 0;
  color: var(--pes-muted);
}

.pes-cover__meta {
  display: grid;
  gap: 0.55rem 1.25rem;
  margin: 1.25rem 0 0;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
}

.pes-cover__meta dt {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--pes-muted);
}

.pes-cover__meta dd {
  margin: 0.1rem 0 0;
  font-weight: 600;
}

.pes-badge {
  display: inline-block;
  margin-top: 1rem;
  padding: 0.3rem 0.7rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border: 1px solid transparent;
}

.pes-badge--green {
  background: #ecfdf5;
  color: var(--pes-green);
  border-color: #a7f3d0;
}

.pes-badge--amber {
  background: #fffbeb;
  color: var(--pes-amber);
  border-color: #fcd34d;
}

.pes-section {
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--pes-line);
}

.pes-section h2 {
  margin: 0 0 0.85rem;
  font-size: 0.8rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--pes-navy);
  border-bottom: 2px solid #b3e5fc;
  padding-bottom: 0.35rem;
}

.pes-section h3 {
  margin: 1rem 0 0.5rem;
  font-size: 0.95rem;
  color: var(--pes-blue);
  font-weight: 700;
}

.pes-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
  gap: 0.65rem;
}

.pes-kpi {
  background: var(--pes-sky);
  border: 1px solid #e0f2fe;
  padding: 0.75rem 0.7rem;
}

.pes-kpi__label {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--pes-blue);
}

.pes-kpi__value {
  margin-top: 0.25rem;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--pes-ink);
}

.pes-summary,
.pes-panel,
.pes-narrative {
  background: #f8fafc;
  border: 1px solid var(--pes-line);
  padding: 0.85rem 1rem;
}

.pes-summary {
  margin-top: 0.85rem;
}

.pes-kv {
  display: grid;
  gap: 0.55rem 1rem;
  margin: 0;
}

.pes-kv > div {
  display: grid;
  grid-template-columns: minmax(10rem, 42%) 1fr;
  gap: 0.75rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid #eef2f7;
}

.pes-kv dt {
  color: #475569;
}

.pes-kv dd {
  margin: 0;
  font-weight: 700;
}

.pes-kv--method dd {
  font-weight: 500;
}

.pes-bars {
  margin-top: 0.5rem;
}

.pes-bar {
  margin: 0.45rem 0;
}

.pes-bar__label {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.2rem;
  font-size: 0.9rem;
}

.pes-bar__track {
  height: 0.45rem;
  background: #e2e8f0;
}

.pes-bar__fill {
  height: 100%;
  background: var(--pes-blue);
}

.pes-bar__fill--green {
  background: #059669;
}

.pes-note {
  margin: 0.55rem 0 0;
  font-size: 0.8rem;
  color: var(--pes-muted);
}

.pes-muted {
  color: var(--pes-muted);
}

.pes-warn {
  background: #fffbeb;
  border-left: 3px solid #d97706;
  color: #92400e;
  padding: 0.65rem 0.8rem;
  font-size: 0.85rem;
  margin: 0 0 0.75rem;
}

.pes-pos {
  color: var(--pes-green);
}

.pes-amber {
  color: var(--pes-amber);
}

.pes-narrative {
  white-space: pre-wrap;
  line-height: 1.6;
  margin-top: 0.35rem;
}

.pes-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.35rem;
}

.pes-chip {
  border: 1px solid var(--pes-line);
  background: #fff;
  padding: 0.25rem 0.55rem;
  font-size: 0.8rem;
}

.pes-dist + .pes-dist {
  margin-top: 0.85rem;
}

@media print {
  .pes-cover {
    break-after: page;
  }
  .pes-section {
    break-inside: avoid;
  }
}
</style>
