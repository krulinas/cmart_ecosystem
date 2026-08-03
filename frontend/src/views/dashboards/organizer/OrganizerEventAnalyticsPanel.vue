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

        <!-- Overview (includes former Revenue & Payments) -->
        <section v-if="activeTab === 'overview'" class="space-y-3" data-testid="analytics-overview">
          <AnalyticsDataSourceBadge :sources="dataSources" compact />

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
            <!-- Financial performance -->
            <div
              ref="financeSectionRef"
              class="rounded-xl border border-sky-100 bg-white p-3"
              data-testid="overview-finance"
            >
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="text-sm font-extrabold text-ink-900">Financial performance</h3>
                  <p class="mt-0.5 text-xs text-ink-500">Platform fees for this event only</p>
                </div>
                <div
                  v-if="hasInvoices"
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

              <template v-if="operationalReady && hasInvoices">
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
                <div class="mt-3">
                  <div
                    class="flex h-3 overflow-hidden rounded-full bg-ink-100"
                    :title="financeBarTitle"
                    role="img"
                    :aria-label="financeBarTitle"
                  >
                    <div
                      v-if="financeBar.collectedPct > 0"
                      class="h-full bg-emerald-500 transition-all"
                      :style="{ width: `${financeBar.collectedPct}%` }"
                    />
                    <div
                      v-if="financeBar.outstandingPct > 0"
                      class="h-full bg-rose-400 transition-all"
                      :style="{ width: `${financeBar.outstandingPct}%` }"
                    />
                  </div>
                  <div class="mt-1.5 flex flex-wrap gap-3 text-[11px] text-ink-500">
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500" />Collected</span>
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-rose-400" />Outstanding</span>
                  </div>
                </div>
              </template>
              <div v-else class="mt-3 space-y-2 text-sm text-ink-600">
                <p v-if="!operationalReady">Operational payment data is unavailable for this event.</p>
                <p v-else-if="!Number(approvedCount)">
                  No approved bookings yet —
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

            <!-- Vendor categories -->
            <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="overview-categories">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="text-sm font-extrabold text-ink-900">Approved bookings by category</h3>
                  <p class="mt-0.5 text-xs text-ink-500">Booking counts, not invoice revenue</p>
                </div>
                <button
                  type="button"
                  class="text-xs font-semibold text-brand-700 underline-offset-2 hover:underline"
                  @click="goToBookings({ status: 'Approved' })"
                >
                  View Bookings
                </button>
              </div>

              <ul v-if="categoryRows.length" class="mt-3 space-y-2">
                <li v-for="row in categoryRows" :key="row.key || row.label">
                  <button
                    type="button"
                    class="w-full rounded-lg px-1 py-0.5 text-left transition hover:bg-sky-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                    :title="`${row.label}: ${row.display}`"
                    @click="goToBookings({ status: 'Approved' })"
                  >
                    <div class="mb-1 flex justify-between gap-2 text-xs">
                      <span class="font-semibold text-ink-800">{{ row.label }}</span>
                      <span class="shrink-0 text-ink-600">{{ row.display }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-sky-50">
                      <div
                        class="h-full rounded-full bg-brand-600"
                        :style="{ width: `${Math.min(Number(row.percent) || 0, 100)}%` }"
                      />
                    </div>
                  </button>
                </li>
              </ul>
              <p v-else class="mt-3 text-sm text-ink-500">
                No approved bookings for this event yet.
              </p>
            </div>
          </div>

          <!-- Survey snapshot -->
          <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="overview-survey-snapshot">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h3 class="text-sm font-extrabold text-ink-900">Survey snapshot</h3>
                <p class="mt-0.5 text-xs text-ink-500">
                  <template v-if="respondentCount != null">n = {{ respondentCount }} respondents</template>
                  <template v-else>Active CSV survey summary</template>
                </p>
              </div>
              <button
                type="button"
                class="text-xs font-semibold text-brand-700 underline-offset-2 hover:underline"
                @click="setActiveTab('vendors')"
              >
                View Survey Insights
              </button>
            </div>
            <p v-if="surveyTopInsight" class="mt-2 text-sm text-ink-700">
              {{ surveyTopInsight }}
            </p>
            <p v-else class="mt-2 text-sm text-ink-500">
              {{ surveyEmpty
                ? (overview?.survey?.message || 'No vendor survey imported for this event.')
                : 'Open Vendors & Sales for full survey charts.' }}
            </p>
          </div>
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
const financeMetric = ref('amount');
const financeSectionRef = ref(null);

function readStoredTab() {
  try {
    let tab = sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY);
    if (tab === 'revenue') tab = 'overview';
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

const hasInvoices = computed(() => Number(payments.value?.invoice_count || 0) > 0);

const financeBar = computed(() => {
  if (financeMetric.value === 'count') {
    const paid = Number(payments.value?.paid_count || 0);
    const unpaid = Number(payments.value?.unpaid_count || 0);
    const total = paid + unpaid;
    if (!total) return { collectedPct: 0, outstandingPct: 0 };
    return {
      collectedPct: Math.round((paid / total) * 1000) / 10,
      outstandingPct: Math.round((unpaid / total) * 1000) / 10,
    };
  }
  const expected = Number(payments.value?.expected || 0);
  const collected = Number(payments.value?.collected || 0);
  const outstanding = Number(payments.value?.outstanding || 0);
  if (!expected) return { collectedPct: 0, outstandingPct: 0 };
  return {
    collectedPct: Math.min(100, Math.round((collected / expected) * 1000) / 10),
    outstandingPct: Math.min(100, Math.round((outstanding / expected) * 1000) / 10),
  };
});

const financeBarTitle = computed(() => {
  if (financeMetric.value === 'count') {
    return `${payments.value?.paid_count ?? 0} paid · ${payments.value?.unpaid_count ?? 0} unpaid invoices`;
  }
  return `Collected RM ${formatMoney(payments.value?.collected)} · Outstanding RM ${formatMoney(payments.value?.outstanding)}`;
});

const surveyTopInsight = computed(() => {
  if (overview.value?.survey?.status !== 'ready') return null;
  const cats = surveySection('vendors')?.product_categories;
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

const overviewKpis = computed(() => [
  {
    id: 'survey_respondents',
    label: 'Survey respondents',
    value: overview.value?.survey?.status === 'ready' ? String(respondentCount.value ?? 0) : '—',
    note: overview.value?.survey?.status === 'ready'
      ? 'View survey insights'
      : (overview.value?.survey?.message || 'No survey yet'),
    title: 'Open Vendors & Sales',
    clickable: true,
    onClick: () => setActiveTab('vendors'),
  },
  {
    id: 'approved_bookings',
    label: 'Approved bookings',
    value: operationalReady.value ? String(approvedCount.value ?? 0) : '—',
    note: Number(approvedCount.value) ? 'Open bookings' : 'No approved bookings yet',
    title: 'Open Bookings for this event',
    clickable: operationalReady.value,
    onClick: () => goToBookings({ status: 'Approved' }),
  },
  {
    id: 'expected_revenue',
    label: 'Expected revenue',
    value: operationalReady.value ? `RM ${formatMoney(payments.value?.expected)}` : '—',
    note: 'Platform fees',
    title: 'Jump to financial performance',
    clickable: operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collected_revenue',
    label: 'Collected revenue',
    value: operationalReady.value ? `RM ${formatMoney(payments.value?.collected)}` : '—',
    note: 'Paid invoices',
    title: 'Jump to financial performance',
    clickable: operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'outstanding_revenue',
    label: 'Outstanding revenue',
    value: operationalReady.value ? `RM ${formatMoney(payments.value?.outstanding)}` : '—',
    note: 'Unpaid invoices',
    title: 'Jump to financial performance',
    clickable: operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collection_rate',
    label: 'Collection rate',
    value: operationalReady.value ? collectionRateLabel.value : '—',
    note: hasInvoices.value
      ? `${payments.value?.paid_count ?? 0}/${payments.value?.invoice_count ?? 0} paid`
      : 'Appears after invoices',
    title: 'Show payment-status breakdown',
    clickable: operationalReady.value,
    onClick: showPaymentBreakdown,
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
