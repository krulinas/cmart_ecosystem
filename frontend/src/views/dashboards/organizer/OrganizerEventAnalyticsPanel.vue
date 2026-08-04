<template>
  <div class="space-y-4" data-testid="organizer-event-analytics-hub">
    <header class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <p class="text-[11px] font-bold uppercase tracking-wider text-brand-700">Analytics Hub</p>
          <h2 class="mt-0.5 truncate text-xl font-extrabold text-ink-900">
            {{ currentEvent?.title || 'Select an event' }}
          </h2>
          <p v-if="currentEvent" class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-600">
            <span>Status: <strong class="text-ink-800">{{ currentEvent.status || 'Unknown' }}</strong></span>
            <span>{{ formatDateRange(currentEvent.starts_at, currentEvent.ends_at) }}</span>
            <span v-if="overview?.computed_at">Updated {{ formatDate(overview.computed_at) }}</span>
            <span v-if="sourceModeLabel">Source: <strong class="text-ink-800">{{ sourceModeLabel }}</strong></span>
          </p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
          <label class="block min-w-[14rem] flex-1 sm:flex-none">
            <span class="mb-1 block text-[11px] font-semibold uppercase text-ink-500">Event</span>
            <select v-model="selectedEventId" class="ml-input w-full text-sm" :disabled="loadingEvents">
              <option value="">Select an event…</option>
              <option v-for="event in events" :key="event.id" :value="String(event.id)">
                {{ event.title }}
              </option>
            </select>
          </label>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="!selectedEventId || loadingOverview"
            @click="refreshAll"
          >
            {{ loadingOverview ? 'Refreshing…' : 'Refresh' }}
          </button>
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="!selectedEventId"
            @click="goToReportCentre"
          >
            Generate Event Report
          </button>
        </div>
      </div>
    </header>

    <p
      v-if="!selectedEventId"
      class="rounded-2xl border border-dashed border-sky-200 bg-sky-50/40 px-4 py-8 text-center text-sm text-ink-600"
    >
      Select an event to view event-scoped analytics.
    </p>

    <template v-else>
      <div
        v-if="loadingOverview && !overview"
        class="rounded-2xl border border-ink-100 bg-white px-4 py-8 text-center text-sm text-ink-500"
      >
        Loading analytics…
      </div>

      <template v-else>
        <p v-if="overviewError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
          {{ overviewError }}
        </p>

        <nav
          class="flex gap-1 overflow-x-auto rounded-xl border border-sky-100 bg-white p-1 shadow-sm"
          aria-label="Analytics sections"
        >
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
            :class="activeTab === tab.id
              ? 'bg-brand-600 text-white shadow-sm'
              : 'text-ink-600 hover:bg-sky-50 hover:text-brand-800'"
            @click="setActiveTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </nav>

        <p
          v-if="surveyDegraded"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
          Survey analytics temporarily unavailable.
          {{ overview?.survey?.message || 'Operational metrics below remain usable where available.' }}
        </p>

        <p
          v-if="overview?.survey?.small_sample"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
        >
          Small sample: n = {{ overview.survey.respondent_count }}
          (threshold {{ overview.survey.small_sample_threshold }}). Interpret percentages carefully.
        </p>

        <!-- Overview -->
        <section v-if="activeTab === 'overview'" class="space-y-3" data-testid="analytics-overview">
          <AnalyticsDataSourceBadge :sources="dataSources" compact />

          <div
            v-if="showAddSurveyCta"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-brand-200 bg-brand-50/40 px-4 py-3"
          >
            <div>
              <p class="text-sm font-semibold text-ink-900">Add Survey Data</p>
              <p class="text-xs text-ink-500">Connect a vendor post-event CSV to unlock Survey Results.</p>
            </div>
            <button type="button" class="ml-btn-primary text-sm" @click="setActiveTab('data-sources')">
              Add Survey Data
            </button>
          </div>

          <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <button
              v-for="card in overviewKpis"
              :key="card.id"
              type="button"
              class="rounded-xl border border-sky-100 bg-white px-3 py-3 text-left shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
              :class="card.clickable
                ? 'cursor-pointer hover:border-brand-300 hover:bg-sky-50/60'
                : 'cursor-default'"
              :disabled="!card.clickable"
              :title="card.title"
              @click="card.clickable && card.onClick()"
            >
              <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">{{ card.label }}</p>
              <p class="mt-1 text-xl font-extrabold text-ink-900">{{ card.value }}</p>
              <p v-if="card.note" class="mt-0.5 text-xs text-ink-500">{{ card.note }}</p>
            </button>
          </div>

          <div class="grid gap-3 lg:grid-cols-2">
            <div
              ref="financeSectionRef"
              class="rounded-xl border border-sky-100 bg-white p-3"
              data-testid="overview-finance"
            >
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="text-sm font-extrabold text-ink-900">Financial performance</h3>
                  <p class="mt-0.5 text-xs text-ink-500">Platform booking revenue for this event only</p>
                </div>
                <div
                  v-if="systemIncluded && hasInvoices"
                  class="flex rounded-lg border border-ink-200 p-0.5 text-[11px] font-semibold"
                  role="group"
                  aria-label="Finance metric"
                >
                  <button
                    type="button"
                    class="rounded-md px-2 py-1 transition"
                    :class="financeMetric === 'amount' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
                    @click="financeMetric = 'amount'"
                  >
                    Amount (RM)
                  </button>
                  <button
                    type="button"
                    class="rounded-md px-2 py-1 transition"
                    :class="financeMetric === 'count' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
                    @click="financeMetric = 'count'"
                  >
                    Invoice count
                  </button>
                </div>
              </div>

              <template v-if="systemIncluded && operationalReady && hasInvoices">
                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                  <div>
                    <dt class="text-ink-500">Expected</dt>
                    <dd class="font-bold text-ink-900">RM {{ formatMoney(payments?.expected) }}</dd>
                  </div>
                  <div>
                    <dt class="text-ink-500">Collected</dt>
                    <dd class="font-bold text-emerald-700">RM {{ formatMoney(payments?.collected) }}</dd>
                  </div>
                  <div>
                    <dt class="text-ink-500">Outstanding</dt>
                    <dd class="font-bold text-rose-700">RM {{ formatMoney(payments?.outstanding) }}</dd>
                  </div>
                  <div>
                    <dt class="text-ink-500">Collection rate</dt>
                    <dd class="font-bold text-ink-900">{{ collectionRateLabel }}</dd>
                  </div>
                </dl>
                <p class="mt-2 text-xs text-ink-500">
                  {{ payments?.paid_count ?? 0 }} paid · {{ payments?.unpaid_count ?? 0 }} unpaid
                  · {{ payments?.invoice_count ?? 0 }} invoices
                </p>
              </template>
              <div v-else class="mt-3 space-y-2 text-sm text-ink-600">
                <p v-if="!systemIncluded">Payments excluded by source mode.</p>
                <p v-else-if="!operationalReady">Booking data unavailable for this event.</p>
                <p v-else-if="!Number(approvedCount)">
                  0 approved bookings —
                  <button type="button" class="font-semibold text-brand-700 underline-offset-2 hover:underline" @click="goToBookings()">
                    View Bookings
                  </button>
                </p>
                <p v-else>
                  No invoices have been generated for this event.
                  Collection rate will appear after an invoice is issued.
                </p>
              </div>
            </div>

            <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="overview-highlights">
              <h3 class="text-sm font-extrabold text-ink-900">Highlights</h3>
              <div class="mt-3 space-y-3 text-sm text-ink-700">
                <div>
                  <p class="text-[11px] font-semibold uppercase text-ink-500">Survey</p>
                  <p v-if="surveyTopInsight">{{ surveyTopInsight }}</p>
                  <p v-else-if="surveyExcluded">Survey excluded by source mode.</p>
                  <p v-else-if="surveyMissing">No survey CSV connected.</p>
                  <p v-else-if="surveyReady">Survey ready · n = {{ respondentCount }}</p>
                  <p v-else>Survey data unavailable.</p>
                </div>
                <div>
                  <p class="text-[11px] font-semibold uppercase text-ink-500">Operations</p>
                  <p v-if="!systemIncluded">Operations excluded by source mode.</p>
                  <p v-else-if="!operationalReady">Operational data unavailable.</p>
                  <p v-else>
                    {{ approvedCount ?? 0 }} approved bookings
                    <template v-if="sites?.total != null"> · {{ sites.total }} sites</template>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Survey Results -->
        <section v-else-if="activeTab === 'survey-results'" class="space-y-3">
          <SurveyResultsPanel
            :overview="overview"
            :sources="dataSources"
            :respondent-count="respondentCount"
            :survey-empty="surveyEmpty"
            :show-add-csv-cta="showAddSurveyCta || csvOnlyOnboarding"
            @open-data-sources="setActiveTab('data-sources')"
          />
        </section>

        <!-- Vendor Comments -->
        <section v-else-if="activeTab === 'comments'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <EventCommentsWordCloud
            :event-id="selectedEventId"
            :qualitative="qualitativeComments"
            :respondent-count="respondentCount"
            :feedback-link-ready="feedbackLinkReady"
            :survey-status="overview?.survey?.status || ''"
          />
        </section>

        <!-- Operations -->
        <section v-else-if="activeTab === 'operations'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="system" />
          <div
            v-if="!systemIncluded"
            class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
          >
            Operations are hidden because the current source mode excludes System Data.
          </div>
          <div
            v-else-if="!operationalReady"
            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
          >
            {{ overview?.operational?.error || 'Operational snapshot unavailable for this event.' }}
          </div>
          <template v-else>
            <div
              v-if="!hasOperationalRecords"
              class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
            >
              No operational records have been created for this event yet.
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">Total bookings</p>
                <p class="mt-1 text-xl font-extrabold">{{ pipeline?.total_bookings ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">Approved</p>
                <p class="mt-1 text-xl font-extrabold">{{ approvedCount ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">Sites / slots</p>
                <p class="mt-1 text-xl font-extrabold">{{ sites?.total ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">Item reservations</p>
                <p class="mt-1 text-xl font-extrabold">
                  {{ reservations?.available === false ? 'Unavailable' : (reservations?.total ?? 0) }}
                </p>
              </article>
            </div>
            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsBarList
                title="Bookings by approval status"
                :rows="bookingStatusRows"
                :denominator="pipeline?.total_bookings || null"
                empty-text="0 bookings recorded for this event."
              />
              <AnalyticsBarList
                title="Sites by operational status"
                :rows="siteStatusRows"
                :denominator="sites?.total || null"
                empty-text="No site layout data for this event."
              />
            </div>
            <div class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-3 text-sm text-ink-600">
              Attendance / check-in:
              <strong>Unavailable</strong>
              — event-level check-in totals are not aggregated in this hub yet.
            </div>
          </template>
        </section>

        <!-- Data Sources -->
        <section v-else-if="activeTab === 'data-sources'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" />
          <AnalyticsDataSourceManager
            :event-id="selectedEventId"
            :event-title="currentEvent?.title || ''"
            :overview="overview"
            @updated="onDataSourceUpdated"
            @view-survey-results="setActiveTab('survey-results')"
          />
        </section>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AnalyticsBarList from '../../../components/analytics/AnalyticsBarList.vue';
import AnalyticsDataSourceBadge from '../../../components/analytics/AnalyticsDataSourceBadge.vue';
import AnalyticsDataSourceManager from '../../../components/analytics/AnalyticsDataSourceManager.vue';
import EventCommentsWordCloud from '../../../components/analytics/EventCommentsWordCloud.vue';
import SurveyResultsPanel from '../../../components/analytics/SurveyResultsPanel.vue';
import { useEventAnalyticsContext } from '../../../composables/useEventAnalyticsContext';
import { ANALYTICS_HUB_TAB_STORAGE_KEY } from '../../../config/workspaceNav';
import {
  getEventAnalyticsOverview,
  listCarbootEventsForAnalytics,
  recomputeEventAnalytics,
} from '../../../services/eventAnalyticsApi';

const toast = useToast();
const router = useRouter();
const { selectedEventId, setSelectedEvent, setSelectedEventId } = useEventAnalyticsContext();

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'survey-results', label: 'Survey Results' },
  { id: 'comments', label: 'Vendor Comments' },
  { id: 'operations', label: 'Operations' },
  { id: 'data-sources', label: 'Data Sources' },
];

const TAB_IDS = new Set(tabs.map((t) => t.id));

const LEGACY_TAB_MAP = {
  revenue: 'overview',
  vendors: 'survey-results',
  items: 'survey-results',
  experience: 'survey-results',
  'data-quality': 'data-sources',
  'data-sources': 'data-sources',
  comments: 'comments',
  'survey-results': 'survey-results',
  operations: 'operations',
  overview: 'overview',
};

const events = ref([]);
const overview = ref(null);
const loadingEvents = ref(false);
const loadingOverview = ref(false);
const overviewError = ref('');
const activeTab = ref(readStoredTab());
const financeMetric = ref('amount');
const financeSectionRef = ref(null);

function normalizeTab(tab) {
  if (!tab) return 'overview';
  const mapped = LEGACY_TAB_MAP[tab] || tab;
  return TAB_IDS.has(mapped) ? mapped : 'overview';
}

function readStoredTab() {
  try {
    return normalizeTab(sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY));
  } catch {
    return 'overview';
  }
}

const setActiveTab = (tabId) => {
  const next = normalizeTab(tabId);
  activeTab.value = next;
  try {
    sessionStorage.setItem(ANALYTICS_HUB_TAB_STORAGE_KEY, next);
  } catch {
    /* ignore */
  }
};

const currentEvent = computed(() =>
  events.value.find((e) => String(e.id) === String(selectedEventId.value)) || null,
);

const sourceMode = computed(() => overview.value?.analytics_source_mode || 'system_only');

const sourceModeLabel = computed(() => {
  switch (sourceMode.value) {
    case 'system_only': return 'System Data';
    case 'csv_only': return 'Survey CSV Only';
    case 'combined': return 'System + Survey CSV';
    default: return sourceMode.value;
  }
});

const systemIncluded = computed(() =>
  sourceMode.value === 'system_only' || sourceMode.value === 'combined',
);

const surveyIncluded = computed(() =>
  sourceMode.value === 'csv_only' || sourceMode.value === 'combined',
);

const surveyDegraded = computed(() => overview.value?.survey?.degraded === true
  || overview.value?.survey?.status === 'degraded');

const surveyReady = computed(() => overview.value?.survey?.status === 'ready');
const surveyExcluded = computed(() => overview.value?.survey?.status === 'excluded');
const surveyMissing = computed(() =>
  ['missing_source', 'empty'].includes(overview.value?.survey?.status)
  || (surveyIncluded.value && !surveyReady.value && !surveyDegraded.value && !surveyExcluded.value),
);

const surveyEmpty = computed(() => !surveyReady.value);

const respondentCount = computed(() => {
  if (!surveyReady.value) return null;
  return overview.value?.survey?.respondent_count ?? null;
});

const showAddSurveyCta = computed(() =>
  surveyIncluded.value
  && !surveyReady.value
  && !surveyExcluded.value
  && sourceMode.value !== 'csv_only',
);

const csvOnlyOnboarding = computed(() =>
  sourceMode.value === 'csv_only' && !surveyReady.value,
);

const operationalReady = computed(() => overview.value?.operational?.available === true);
const payments = computed(() => overview.value?.operational?.sections?.payments || null);
const pipeline = computed(() => overview.value?.operational?.sections?.booking_pipeline || null);
const sites = computed(() => overview.value?.operational?.sections?.event_sites || null);
const reservations = computed(() => overview.value?.operational?.sections?.item_reservations || null);
const approvedCount = computed(() => pipeline.value?.approved_count ?? null);

const hasOperationalRecords = computed(() =>
  Number(pipeline.value?.total_bookings || 0) > 0
  || Number(sites.value?.total || 0) > 0
  || Number(reservations.value?.total || 0) > 0,
);

const feedbackLinkReady = computed(() =>
  Boolean(overview.value?.data_readiness?.checks?.community_feedback_event_link?.ready),
);

const qualitativeComments = computed(() =>
  overview.value?.survey?.sections?.experience?.qualitative_comments || null,
);

const dataSources = computed(() => overview.value?.data_sources || []);

const collectionRateLabel = computed(() => {
  const expected = Number(payments.value?.expected || 0);
  const collected = Number(payments.value?.collected || 0);
  if (!expected) return 'No invoices yet';
  return `${((collected / expected) * 100).toFixed(1)}%`;
});

const hasInvoices = computed(() => Number(payments.value?.invoice_count || 0) > 0);

const surveyTopInsight = computed(() => {
  if (!surveyReady.value) return null;
  const cats = overview.value?.survey?.sections?.vendors?.product_categories;
  const rows = Array.isArray(cats) ? cats : [];
  if (!rows.length) return null;
  const top = [...rows].sort((a, b) => Number(b.count || 0) - Number(a.count || 0))[0];
  if (!top?.label) return null;
  const pct = top.percent != null ? `${top.percent}%` : null;
  return pct
    ? `Top product category: ${top.label} (${pct} of respondents).`
    : `Top product category: ${top.label}.`;
});

const scrollToFinance = () => {
  financeSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
  financeMetric.value = 'amount';
};

const showPaymentBreakdown = () => {
  financeSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
  financeMetric.value = 'count';
};

const goToBookings = ({ status } = {}) => {
  if (!selectedEventId.value) return;
  try {
    sessionStorage.setItem('cmart.bookings.preselectEventId', String(selectedEventId.value));
    if (status) {
      sessionStorage.setItem('cmart.bookings.preselectStatus', status);
    } else {
      sessionStorage.removeItem('cmart.bookings.preselectStatus');
    }
  } catch {
    /* ignore */
  }
  router.push({ path: '/admin', hash: '#bookings' });
};

const kpiSurveyValue = computed(() => {
  if (!surveyIncluded.value) return 'Excluded';
  if (surveyReady.value) return String(respondentCount.value ?? 0);
  if (surveyMissing.value) return 'No CSV';
  if (surveyDegraded.value) return 'Unavailable';
  return 'Unavailable';
});

const kpiBookingsValue = computed(() => {
  if (!systemIncluded.value) return 'Excluded';
  if (!operationalReady.value) return 'Unavailable';
  return String(approvedCount.value ?? 0);
});

const overviewKpis = computed(() => [
  {
    id: 'survey_respondents',
    label: 'Survey respondents',
    value: kpiSurveyValue.value,
    note: surveyReady.value
      ? 'View Survey Results'
      : (surveyIncluded.value ? (overview.value?.survey?.message || 'No survey CSV connected') : 'Hidden by source mode'),
    title: 'Open Survey Results',
    clickable: true,
    onClick: () => setActiveTab(surveyReady.value ? 'survey-results' : 'data-sources'),
  },
  {
    id: 'approved_bookings',
    label: 'Approved bookings',
    value: kpiBookingsValue.value,
    note: !systemIncluded.value
      ? 'Excluded by source mode'
      : (Number(approvedCount.value) ? 'Open bookings' : '0 approved bookings'),
    title: 'Open Bookings for this event',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: () => goToBookings({ status: 'Approved' }),
  },
  {
    id: 'expected_revenue',
    label: 'Expected platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.expected)}`),
    note: 'Platform fees',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collected_revenue',
    label: 'Collected platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.collected)}`),
    note: 'Paid invoices',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'outstanding_revenue',
    label: 'Outstanding platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.outstanding)}`),
    note: 'Unpaid invoices',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collection_rate',
    label: 'Collection rate',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : collectionRateLabel.value),
    note: hasInvoices.value
      ? `${payments.value?.paid_count ?? 0}/${payments.value?.invoice_count ?? 0} paid`
      : 'Appears after invoices',
    title: 'Show payment-status breakdown',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: showPaymentBreakdown,
  },
]);

const bookingStatusRows = computed(() => {
  const by = pipeline.value?.by_approval_status || {};
  const total = pipeline.value?.total_bookings || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: key.replace(/_/g, ' '),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? `${count} of ${total} (${((count / total) * 100).toFixed(1)}%)` : `${count}`,
  }));
});

const siteStatusRows = computed(() => {
  const by = sites.value?.by_operational_status || {};
  const total = sites.value?.total || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: key.replace(/_/g, ' '),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? `${count} of ${total} (${((count / total) * 100).toFixed(1)}%)` : `${count}`,
  }));
});

const formatMoney = (value) => {
  if (value == null || value === '') return '0.00';
  const n = Number(value);
  if (Number.isNaN(n)) return '0.00';
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (value) => {
  if (!value) return 'Unknown';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const formatDateRange = (start, end) => {
  if (!start && !end) return 'Dates not set';
  const fmt = (v) => {
    try {
      return new Date(v).toLocaleDateString();
    } catch {
      return v;
    }
  };
  if (start && end) return `${fmt(start)} – ${fmt(end)}`;
  return fmt(start || end);
};

const unwrapEvents = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
};

const loadEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await listCarbootEventsForAnalytics();
    events.value = unwrapEvents(data);
    if (selectedEventId.value) {
      const match = events.value.find((e) => String(e.id) === String(selectedEventId.value));
      if (match) setSelectedEvent(match);
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Unable to load events.');
  } finally {
    loadingEvents.value = false;
  }
};

const loadOverview = async (recompute = false) => {
  if (!selectedEventId.value) {
    overview.value = null;
    return;
  }
  loadingOverview.value = true;
  overviewError.value = '';
  try {
    const { data } = recompute
      ? await recomputeEventAnalytics(selectedEventId.value)
      : await getEventAnalyticsOverview(selectedEventId.value);
    overview.value = data;
    if (currentEvent.value) setSelectedEvent(currentEvent.value);
  } catch (e) {
    overview.value = null;
    overviewError.value = e.response?.data?.message || 'Unable to load event analytics.';
  } finally {
    loadingOverview.value = false;
  }
};

const refreshAll = () => loadOverview(true);

const onDataSourceUpdated = (nextOverview) => {
  if (nextOverview) {
    overview.value = nextOverview;
    return;
  }
  loadOverview(true);
};

const goToReportCentre = () => {
  if (!selectedEventId.value) return;
  try {
    sessionStorage.setItem('cmart.reportCentre.preselectEventId', String(selectedEventId.value));
  } catch {
    /* ignore */
  }
  router.push({ path: '/admin', hash: '#report-centre' });
};

watch(selectedEventId, (id) => {
  setSelectedEventId(id);
  loadOverview(false);
});

onMounted(async () => {
  try {
    const redirectedTab = sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY);
    if (redirectedTab) activeTab.value = normalizeTab(redirectedTab);
  } catch {
    /* ignore */
  }
  await loadEvents();
  if (selectedEventId.value) await loadOverview(false);
});

defineExpose({
  refresh: refreshAll,
  setActiveTab,
});
</script>
