<template>
  <div class="space-y-4" data-testid="organizer-event-analytics-hub">
    <!-- Compact event header -->
    <header class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <p class="text-[11px] font-bold uppercase tracking-wider text-brand-700">Analytics Hub</p>
          <h2 class="mt-0.5 truncate text-xl font-extrabold text-ink-900">
            {{ currentEvent?.title || 'Select an event' }}
          </h2>
          <p v-if="currentEvent" class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-600">
            <span>Status: <strong class="text-ink-800">{{ currentEvent.status || '—' }}</strong></span>
            <span>{{ formatDateRange(currentEvent.starts_at, currentEvent.ends_at) }}</span>
            <span v-if="overview?.computed_at">Updated {{ formatDate(overview.computed_at) }}</span>
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
            :title="!selectedEventId ? 'Select an event first' : 'Refresh analytics for this event'"
            @click="refreshAll"
          >
            {{ loadingOverview ? 'Refreshing…' : 'Refresh' }}
          </button>
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="!selectedEventId"
            :title="!selectedEventId ? 'Select an event first' : 'Open Report Centre with this event'"
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

        <div
          v-if="surveyDegraded"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
          Survey analytics temporarily unavailable.
          {{ overview?.survey?.message || 'Operational metrics below remain usable where available.' }}
        </div>

        <p
          v-if="overview?.survey?.small_sample"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
        >
          Small sample: n = {{ overview.survey.respondent_count }}
          (threshold {{ overview.survey.small_sample_threshold }}). Interpret percentages carefully.
        </p>

        <!-- Overview -->
        <section v-if="activeTab === 'overview'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" />
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in overviewCards" :key="card.label" class="rounded-xl border border-sky-100 bg-white px-3 py-3 shadow-sm">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">{{ card.label }}</p>
              <p class="mt-1 text-xl font-extrabold text-ink-900">{{ card.value }}</p>
              <p v-if="card.note" class="mt-0.5 text-xs text-ink-500">{{ card.note }}</p>
            </article>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-sky-100 bg-white p-3 text-sm">
              <p class="font-bold text-ink-900">Survey readiness</p>
              <p class="mt-1 text-ink-600">{{ surveyReadinessText }}</p>
              <p v-if="respondentCount != null" class="mt-2 text-xs font-semibold text-ink-500">
                Respondents n = {{ respondentCount }}
              </p>
            </div>
            <div class="rounded-xl border border-sky-100 bg-white p-3 text-sm">
              <p class="font-bold text-ink-900">Payment snapshot</p>
              <p class="mt-1 text-ink-600">
                Collected RM {{ formatMoney(payments?.collected) }} ·
                Outstanding RM {{ formatMoney(payments?.outstanding) }}
              </p>
              <p class="mt-2 text-xs text-ink-500">Organizer platform fees for this event only.</p>
            </div>
          </div>
        </section>

        <!-- Revenue -->
        <section v-else-if="activeTab === 'revenue'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="system" />
          <div class="rounded-xl border border-sky-100 bg-sky-50/50 px-3 py-2 text-sm text-ink-700">
            Organizer operational revenue (platform fees) from approved booking invoices for this event only.
            Vendor self-reported sales bands are shown under <strong>Vendors &amp; Sales</strong> and are not converted to exact RM.
          </div>
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Expected</p>
              <p class="mt-1 text-xl font-extrabold text-brand-700">RM {{ formatMoney(payments?.expected) }}</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Collected</p>
              <p class="mt-1 text-xl font-extrabold text-emerald-700">RM {{ formatMoney(payments?.collected) }}</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Outstanding</p>
              <p class="mt-1 text-xl font-extrabold text-rose-700">RM {{ formatMoney(payments?.outstanding) }}</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Collection rate</p>
              <p class="mt-1 text-xl font-extrabold text-ink-900">{{ collectionRateLabel }}</p>
              <p class="mt-0.5 text-xs text-ink-500">{{ payments?.invoice_count ?? 0 }} invoices · {{ approvedCount }} approved bookings</p>
            </article>
          </div>
          <AnalyticsBarList
            title="Approved bookings by vendor category"
            :rows="categoryRows"
            :denominator="approvedCount || null"
            empty-text="No approved bookings for this event yet."
          />
          <p class="text-xs text-ink-500">Category mix uses approved booking counts, not invoice revenue.</p>
        </section>

        <!-- Vendors & Sales -->
        <section v-else-if="activeTab === 'vendors'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <p class="text-sm text-ink-600">
            Vendor survey insights for this event
            <template v-if="respondentCount != null"> · <strong>n = {{ respondentCount }}</strong></template>
          </p>
          <p v-if="surveyEmpty" class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-500">
            {{ overview?.survey?.message || 'No vendor survey imported for this event.' }}
          </p>
          <template v-else>
            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsBarList
                title="Product categories"
                :rows="surveySection('vendors')?.product_categories"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Sales purpose"
                :rows="surveySection('vendors')?.sales_purpose"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Gross sales bands (self-reported)"
                :rows="surveySection('economics')?.gross_sales_band"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Used-item sell-through"
                :rows="surveySection('items')?.items_sold_band"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                class="lg:col-span-2"
                title="Where vendors heard about the event"
                :rows="surveySection('operations')?.event_info_sources"
                :denominator="respondentCount"
              />
            </div>
          </template>
        </section>

        <!-- Items & Reuse -->
        <section v-else-if="activeTab === 'items'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <p v-if="surveyEmpty" class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-500">
            {{ overview?.survey?.message || 'No vendor survey imported for this event.' }}
          </p>
          <template v-else>
            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsBarList
                title="Item conditions"
                :rows="surveySection('vendors')?.item_conditions"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Used-item sell-through"
                :rows="surveySection('items')?.items_sold_band"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                class="lg:col-span-2"
                title="Unsold-item actions"
                :rows="surveySection('items')?.unsold_item_actions"
                :denominator="respondentCount"
              />
            </div>
            <div
              v-if="surveySection('items')?.circularity_proxies"
              class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
            >
              <p class="font-bold text-ink-900">Reuse / circularity proxies</p>
              <p class="mt-1">
                Positive reuse actions:
                <strong>{{ surveySection('items').circularity_proxies.positive_action_display }}</strong>
              </p>
              <p>
                Discarded:
                <strong>{{ surveySection('items').circularity_proxies.discard_action_display }}</strong>
              </p>
              <p class="mt-1 text-xs text-ink-500">{{ surveySection('items').circularity_proxies.note }}</p>
            </div>
          </template>
        </section>

        <!-- Experience -->
        <section v-else-if="activeTab === 'experience'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <p v-if="surveyEmpty" class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-500">
            {{ overview?.survey?.message || 'No vendor survey imported for this event.' }}
          </p>
          <template v-else>
            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsBarList
                title="Experience rating"
                :rows="surveySection('experience')?.experience_rating"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Supporting activity attracted visitors"
                :rows="surveySection('experience')?.supporting_activity_attracted_visitors"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Supporting activity impacts"
                :rows="surveySection('experience')?.supporting_activity_impacts"
                :denominator="respondentCount"
              />
              <AnalyticsBarList
                title="Improvement priorities"
                :rows="surveySection('operations')?.improvement_areas"
                :denominator="respondentCount"
              />
            </div>
            <div
              v-if="surveySection('operations')?.has_difficulty"
              class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
            >
              <p class="font-bold text-ink-900">Vendor difficulties (registration / info)</p>
              <p class="mt-1">
                Yes: <strong>{{ surveySection('operations').has_difficulty.yes_display }}</strong>
                · No: <strong>{{ surveySection('operations').has_difficulty.no_display }}</strong>
              </p>
            </div>
          </template>
        </section>

        <!-- Operations (Laravel operational only) -->
        <section v-else-if="activeTab === 'operations'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="system" />
          <div
            v-if="!operationalReady"
            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
          >
            {{ overview?.operational?.error || 'Operational snapshot unavailable for this event.' }}
          </div>
          <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Total bookings</p>
              <p class="mt-1 text-xl font-extrabold">{{ pipeline?.total_bookings ?? '—' }}</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Approved</p>
              <p class="mt-1 text-xl font-extrabold">{{ approvedCount ?? '—' }}</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Sites / slots</p>
              <p class="mt-1 text-xl font-extrabold">{{ sites?.total ?? '—' }}</p>
              <p class="mt-0.5 text-xs text-ink-500">By status below when available</p>
            </article>
            <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
              <p class="text-[11px] font-semibold uppercase text-ink-500">Item reservations</p>
              <p class="mt-1 text-xl font-extrabold">
                {{ reservations?.available === false ? 'Unavailable' : (reservations?.total ?? '—') }}
              </p>
            </article>
          </div>
          <div class="grid gap-3 lg:grid-cols-2">
            <AnalyticsBarList
              title="Bookings by approval status"
              :rows="bookingStatusRows"
              :denominator="pipeline?.total_bookings || null"
              empty-text="No bookings for this event."
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
        </section>

        <!-- Data Quality -->
        <section v-else-if="activeTab === 'data-quality'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" />
          <AnalyticsDataSourceManager
            :event-id="selectedEventId"
            :overview="overview"
            @updated="onDataSourceUpdated"
          />
        </section>

        <!-- Comments & Word Cloud -->
        <section v-else-if="activeTab === 'comments'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <EventCommentsWordCloud
            :event-id="selectedEventId"
            :comments="surveyComments"
            :respondent-count="respondentCount"
            :feedback-link-ready="feedbackLinkReady"
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
import { useEventAnalyticsContext } from '../../../composables/useEventAnalyticsContext';
import {
  ANALYTICS_HUB_TAB_STORAGE_KEY,
} from '../../../config/workspaceNav';
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
  { id: 'revenue', label: 'Revenue & Payments' },
  { id: 'vendors', label: 'Vendors & Sales' },
  { id: 'items', label: 'Items & Reuse' },
  { id: 'experience', label: 'Experience' },
  { id: 'operations', label: 'Operations' },
  { id: 'data-quality', label: 'Data Quality' },
  { id: 'comments', label: 'Comments & Word Cloud' },
];

const TAB_IDS = new Set(tabs.map((t) => t.id));

const events = ref([]);
const overview = ref(null);
const loadingEvents = ref(false);
const loadingOverview = ref(false);
const overviewError = ref('');
const activeTab = ref(readStoredTab());

function readStoredTab() {
  try {
    const tab = sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY);
    return TAB_IDS.has(tab) ? tab : 'overview';
  } catch {
    return 'overview';
  }
}

const setActiveTab = (tabId) => {
  if (!TAB_IDS.has(tabId)) return;
  activeTab.value = tabId;
  try {
    sessionStorage.setItem(ANALYTICS_HUB_TAB_STORAGE_KEY, tabId);
  } catch {
    /* ignore */
  }
};

const currentEvent = computed(() =>
  events.value.find((e) => String(e.id) === String(selectedEventId.value)) || null,
);

const surveyDegraded = computed(() => overview.value?.survey?.degraded === true
  || overview.value?.survey?.status === 'degraded');

const surveyEmpty = computed(() => overview.value?.survey?.status !== 'ready');

const respondentCount = computed(() => {
  if (overview.value?.survey?.status !== 'ready') return null;
  return overview.value?.survey?.respondent_count ?? null;
});

const operationalReady = computed(() => overview.value?.operational?.available === true);
const payments = computed(() => overview.value?.operational?.sections?.payments || null);
const pipeline = computed(() => overview.value?.operational?.sections?.booking_pipeline || null);
const sites = computed(() => overview.value?.operational?.sections?.event_sites || null);
const reservations = computed(() => overview.value?.operational?.sections?.item_reservations || null);
const approvedCount = computed(() => pipeline.value?.approved_count ?? null);

const feedbackLinkReady = computed(() =>
  Boolean(overview.value?.data_readiness?.checks?.community_feedback_event_link?.ready),
);

const surveyComments = computed(() => {
  const items = overview.value?.survey?.sections?.experience?.comments_and_suggestions?.items;
  return Array.isArray(items) ? items.filter(Boolean) : [];
});

const dataSources = computed(() => overview.value?.data_sources || []);

const collectionRateLabel = computed(() => {
  const expected = Number(payments.value?.expected || 0);
  const collected = Number(payments.value?.collected || 0);
  if (!expected) return '—';
  return `${((collected / expected) * 100).toFixed(1)}%`;
});

const surveyReadinessText = computed(() => {
  const status = overview.value?.survey?.status;
  if (status === 'ready') return `${respondentCount.value} valid survey responses imported.`;
  if (status === 'empty') return 'No survey imported for this event yet.';
  if (status === 'degraded') return overview.value?.survey?.message || 'Survey analytics degraded.';
  return overview.value?.survey?.message || 'Survey analytics unavailable.';
});

const overviewCards = computed(() => [
  {
    label: 'Survey respondents',
    value: overview.value?.survey?.status === 'ready' ? String(respondentCount.value ?? 0) : 'Unavailable',
    note: overview.value?.survey?.status === 'ready' ? `n = ${respondentCount.value}` : surveyReadinessText.value,
  },
  {
    label: 'Approved bookings',
    value: approvedCount ?? '—',
    note: operationalReady.value ? 'This event only' : 'Operational data unavailable',
  },
  {
    label: 'Collected revenue',
    value: operationalReady.value ? `RM ${formatMoney(payments.value?.collected)}` : '—',
    note: 'Platform fees, not vendor sales',
  },
  {
    label: 'Event status',
    value: currentEvent.value?.status || overview.value?.event?.status || '—',
    note: formatDateRange(currentEvent.value?.starts_at, currentEvent.value?.ends_at),
  },
]);

const categoryRows = computed(() => {
  const dist = overview.value?.operational?.sections?.vendor_categories?.distribution || [];
  const total = approvedCount.value || dist.reduce((sum, row) => sum + (row.count || 0), 0) || 0;
  return dist.map((row) => ({
    key: row.label,
    label: row.label,
    count: row.count,
    denominator: total,
    percent: total ? Math.round((row.count / total) * 1000) / 10 : 0,
    display: total ? `${row.count} of ${total} approved (${((row.count / total) * 100).toFixed(1)}%)` : `${row.count}`,
  }));
});

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

const surveySection = (name) => overview.value?.survey?.sections?.[name] || null;

const formatMoney = (value) => {
  if (value == null || value === '') return '—';
  const n = Number(value);
  if (Number.isNaN(n)) return '—';
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const formatDateRange = (start, end) => {
  if (!start && !end) return '—';
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
  // Fallback: force recompute when a mutation did not return an overview payload.
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
  // Consume redirect tab hint set by AdminDashboard legacy hash handling.
  try {
    const redirectedTab = sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY);
    if (TAB_IDS.has(redirectedTab)) activeTab.value = redirectedTab;
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
