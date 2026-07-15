<template>
  <section
    class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm"
    data-testid="organizer-released-day-recovery-panel"
  >
    <div class="px-5 py-4 text-white sm:px-6" :class="theme.recoveryHeader">
      <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">Operational queue</p>
          <h2 class="text-lg font-extrabold tracking-tight">Released-Day Recovery</h2>
          <p class="mt-1 max-w-3xl text-sm text-white/80">
            Partial EventDay site slices released through Organizer attendance exceptions. This queue is read-only and
            does not assign replacement vendors.
          </p>
        </div>
        <span class="mt-2 inline-flex w-fit items-center rounded-full bg-white/15 px-3 py-1 text-xs font-bold ring-1 ring-white/25 sm:mt-0">
          {{ pagination.total }} slice{{ pagination.total === 1 ? '' : 's' }}
        </span>
      </div>
    </div>

    <div class="border-b border-ink-100 bg-ink-50/40 px-4 py-4 sm:px-6">
      <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
        <input
          v-model="searchQuery"
          type="search"
          class="ml-input text-sm lg:col-span-2"
          placeholder="Search booking, event, site, or vendor…"
          data-testid="recovery-search-input"
        />
        <select v-model="recoveryStateFilter" class="ml-input text-sm" data-testid="recovery-state-filter">
          <option value="all">All recovery states</option>
          <option v-for="state in recoveryStateOptions" :key="state" :value="state">
            {{ recoveryStateLabel(state) }}
          </option>
        </select>
        <select v-model="paymentStateFilter" class="ml-input text-sm" data-testid="recovery-payment-filter">
          <option value="all">All payment states</option>
          <option value="paid">Paid</option>
          <option value="payment_submitted">Payment Submitted</option>
          <option value="unpaid">Unpaid</option>
        </select>
      </div>
    </div>

    <div v-if="loading && !hasLoaded" class="px-6 py-16 text-center" data-testid="recovery-loading">
      <div class="mx-auto h-10 w-10 animate-pulse rounded-full bg-gradient-to-br from-cyan-100 to-sky-100" />
      <p class="mt-4 text-sm font-medium text-ink-500">Loading released-day recovery queue…</p>
    </div>

    <div v-else-if="loadError" class="px-6 py-12 text-center text-sm text-rose-700">
      {{ loadError }}
    </div>

    <div
      v-else-if="hasLoaded && !rows.length"
      class="m-6"
      data-testid="recovery-empty-state"
    >
      <ManagementEmptyState
        title="No released slices"
        description="No released EventDay site slices currently require Organizer recovery review."
        icon="↺"
        accent="cyan"
      />
    </div>

    <div v-else class="relative overflow-x-auto">
      <div
        v-if="tableLoading"
        class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 backdrop-blur-[1px]"
      >
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-cyan-200 border-t-cyan-600" />
      </div>
      <table class="min-w-full text-sm" data-testid="recovery-queue-table">
        <thead class="bg-ink-50/60">
          <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500">
            <th class="px-4 py-3 font-semibold">Event</th>
            <th class="px-4 py-3 font-semibold">Released day</th>
            <th class="px-4 py-3 font-semibold">Source booking</th>
            <th class="px-4 py-3 font-semibold">Vendor</th>
            <th class="px-4 py-3 font-semibold">Released sites</th>
            <th class="px-4 py-3 font-semibold">Payment</th>
            <th class="px-4 py-3 font-semibold">Recovery</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100">
          <tr
            v-for="row in rows"
            :key="row.id"
            class="transition hover:bg-ink-50/40"
            data-testid="recovery-queue-row"
            :data-recovery-id="row.id"
            :data-recovery-state="row.recovery_state"
          >
            <td class="px-4 py-3.5 font-semibold text-ink-900">{{ row.event?.title || '—' }}</td>
            <td class="px-4 py-3.5 whitespace-nowrap text-ink-700">
              {{ formatOperationalDate(row.event_day?.operational_date) }}
            </td>
            <td class="px-4 py-3.5">
              <div class="font-bold text-ink-900" data-testid="recovery-source-booking-reference">
                {{ row.source_booking?.reference || '—' }}
              </div>
              <div class="text-xs text-ink-500">{{ statusLabel(row.source_booking?.status) }}</div>
            </td>
            <td class="px-4 py-3.5 text-ink-700">
              {{ row.source_booking?.business_name || row.source_booking?.vendor_name || '—' }}
            </td>
            <td class="px-4 py-3.5 text-ink-700" data-testid="recovery-site-labels">
              {{ releasedSiteLabels(row) }}
            </td>
            <td class="px-4 py-3.5">
              <span :class="recoveryPaymentBadgeClass(row.source_payment_state)">
                {{ recoveryPaymentLabel(row.source_payment_state) }}
              </span>
            </td>
            <td class="px-4 py-3.5">
              <span :class="recoveryStateBadgeClass(row.recovery_state)" data-testid="recovery-state-chip">
                {{ recoveryStateLabel(row.recovery_state) }}
              </span>
              <p v-if="recoveryBlockerSummary(row)" class="mt-1 text-xs text-ink-500" data-testid="recovery-blocker-summary">
                {{ recoveryBlockerSummary(row) }}
              </p>
              <p class="mt-1 text-xs text-ink-400" data-testid="recovery-full-event-availability">
                Standard full-event:
                {{ row.standard_full_event_available ? 'Available' : 'Unavailable' }}
              </p>
            </td>
            <td class="px-4 py-3.5 text-right">
              <button
                type="button"
                class="ml-btn-ghost text-xs px-3 py-1.5"
                data-testid="recovery-view-detail"
                @click="openDetail(row)"
              >
                Detail
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="hasLoaded && pagination.total"
      class="flex flex-col gap-3 border-t border-ink-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      data-testid="recovery-pagination"
    >
      <span class="text-sm text-ink-500">
        Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }}
      </span>
      <div class="flex items-center gap-2">
        <button type="button" class="ml-btn-ghost text-xs" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">
          Previous
        </button>
        <span class="text-xs font-semibold text-ink-600">Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          type="button"
          class="ml-btn-ghost text-xs"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="goToPage(pagination.current_page + 1)"
        >
          Next
        </button>
      </div>
    </div>

    <OrganizerReleasedDayRecoveryModal v-model="showDetail" :item="selectedItem" />
  </section>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../services/api';
import ManagementEmptyState from '../management/ManagementEmptyState.vue';
import OrganizerReleasedDayRecoveryModal from './OrganizerReleasedDayRecoveryModal.vue';
import { useManagementAccess } from '../../composables/useManagementAccess';
import {
  formatOperationalDate,
  recoveryBlockerSummary,
  recoveryPaymentBadgeClass,
  recoveryPaymentLabel,
  recoveryStateBadgeClass,
  recoveryStateLabel,
  recoveryStateOptions,
  releasedSiteLabels,
  statusLabel,
} from '../../utils/bookingDisplay';

const toast = useToast();
const { workspaceTheme } = useManagementAccess();

const theme = computed(() => ({
  recoveryHeader: workspaceTheme.value.queueHeader || 'bg-gradient-to-r from-cyan-700 to-sky-700',
}));

const rows = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
});
const loading = ref(false);
const tableLoading = ref(false);
const hasLoaded = ref(false);
const loadError = ref(null);
const searchQuery = ref('');
const debouncedSearch = ref('');
const recoveryStateFilter = ref('all');
const paymentStateFilter = ref('all');
const currentPage = ref(1);
const showDetail = ref(false);
const selectedItem = ref(null);

let searchDebounceTimer;

const buildParams = () => {
  const params = {
    page: currentPage.value,
    per_page: 15,
    include_audit_timeline: 0,
  };
  if (debouncedSearch.value) params.search = debouncedSearch.value;
  if (recoveryStateFilter.value !== 'all') params.recovery_state = recoveryStateFilter.value;
  if (paymentStateFilter.value !== 'all') params.payment_state = paymentStateFilter.value;
  return params;
};

const fetchQueue = async ({ initial = false } = {}) => {
  if (initial) {
    loading.value = true;
    loadError.value = null;
  } else {
    tableLoading.value = true;
  }

  try {
    const { data } = await api.get('/organizer/released-day-recovery', { params: buildParams() });
    rows.value = data.data ?? [];
    pagination.value = {
      current_page: data.meta?.current_page ?? 1,
      last_page: data.meta?.last_page ?? 1,
      per_page: data.meta?.per_page ?? 15,
      total: data.meta?.total ?? 0,
      from: data.meta?.from ?? null,
      to: data.meta?.to ?? null,
    };
    hasLoaded.value = true;
  } catch (error) {
    loadError.value = error.forbiddenMessage || error.response?.data?.message || 'Unable to load recovery queue.';
    if (!error.forbiddenMessage) toast.error(loadError.value);
    throw error;
  } finally {
    loading.value = false;
    tableLoading.value = false;
  }
};

const load = async () => fetchQueue({ initial: !hasLoaded.value });

const openDetail = async (row) => {
  try {
    const { data } = await api.get('/organizer/released-day-recovery', {
      params: {
        search: row.source_booking?.reference?.replace('BKG-', '') || row.source_booking?.id,
        event_day_id: row.event_day?.id,
        include_audit_timeline: 1,
        per_page: 1,
      },
    });
    selectedItem.value = data.data?.[0] || row;
    showDetail.value = true;
  } catch {
    selectedItem.value = row;
    showDetail.value = true;
  }
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  currentPage.value = page;
};

watch(searchQuery, (value) => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = value.trim();
    currentPage.value = 1;
    if (hasLoaded.value) fetchQueue();
  }, 300);
});

watch([recoveryStateFilter, paymentStateFilter], () => {
  currentPage.value = 1;
  if (hasLoaded.value) fetchQueue();
});

watch(currentPage, () => {
  if (hasLoaded.value) fetchQueue();
});

onMounted(() => {
  load().catch(() => {});
});

defineExpose({ load, fetchQueue });
</script>
