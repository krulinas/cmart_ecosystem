<template>
  <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
      <div>
        <h2 class="text-xl font-extrabold text-ink-900">Booking Receipts</h2>
        <p class="text-sm text-ink-500">Payment records for your booth bookings and issued invoices.</p>
      </div>
      <button
        type="button"
        class="ml-btn-ghost text-sm shrink-0"
        :disabled="loading"
        @click="$emit('retry')"
      >
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading" class="overflow-x-auto rounded-2xl border border-ink-100">
      <table class="min-w-full divide-y divide-ink-100 text-sm">
        <thead class="bg-ink-50/80">
          <tr>
            <th
              v-for="column in columns"
              :key="column"
              scope="col"
              class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500"
              :class="column === 'Amount' ? 'text-right' : column === 'Action' ? 'text-right' : 'text-left'"
            >
              {{ column }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 bg-white/60">
          <tr v-for="n in 3" :key="n" class="animate-pulse">
            <td v-for="column in columns" :key="column" class="px-4 py-4">
              <div class="h-4 rounded bg-ink-100" :class="column === 'Action' ? 'ml-auto w-24' : 'w-28'"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-8 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load your payment records right now.</p>
      <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="$emit('retry')">Try Again</button>
    </div>

    <div
      v-else-if="!records.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
    >
      No payment records yet. Your booking receipts will appear here once an invoice is issued.
    </div>

    <template v-else>
      <div class="mb-4">
        <input
          v-model="receiptsSearchQuery"
          type="search"
          placeholder="Search payment records…"
          class="w-full sm:max-w-sm rounded-xl border border-ink-200 bg-white/80 px-4 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
        />
      </div>

      <div class="flex flex-wrap gap-2 mb-5">
        <button
          v-for="tab in RECEIPT_FILTER_TABS"
          :key="tab.id"
          type="button"
          class="rounded-full px-4 py-1.5 text-sm font-semibold transition"
          :class="filterTabClass(selectedReceiptStatus === tab.id)"
          @click="selectedReceiptStatus = tab.id"
        >
          {{ tab.label }}
          <span class="ml-1 opacity-75">({{ receiptFilterCounts[tab.id] || 0 }})</span>
        </button>
      </div>

      <div
        v-if="!filteredRecords.length"
        class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
      >
        No payment records match your search.
      </div>

      <template v-else>
        <div class="overflow-x-auto rounded-2xl border border-ink-100">
          <table class="min-w-full divide-y divide-ink-100 text-sm">
            <thead class="bg-ink-50/80">
              <tr>
                <th
                  v-for="column in columns"
                  :key="column"
                  scope="col"
                  class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-ink-500"
                  :class="column === 'Amount' || column === 'Action' ? 'text-right' : 'text-left'"
                >
                  {{ column }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 bg-white/60">
              <tr
                v-for="row in visibleRecords"
                :key="`${row.booking_id}-${row.id}`"
                class="hover:bg-brand-50/40 transition-colors"
              >
                <td class="px-4 py-4 font-semibold text-ink-900">{{ row.event }}</td>
                <td class="px-4 py-4 text-ink-600">{{ formatRecordDate(row.date) }}</td>
                <td class="px-4 py-4 text-ink-600">{{ row.booth_number || '—' }}</td>
                <td class="px-4 py-4 text-right font-semibold text-ink-900">RM {{ formatAmount(row.amount) }}</td>
                <td class="px-4 py-4">
                  <span :class="statusBadgeClass(row)">{{ displayStatus(row) }}</span>
                </td>
                <td class="px-4 py-4 text-right">
                  <button
                    v-if="row.receipt_available"
                    type="button"
                    class="ml-btn-ghost text-sm font-semibold"
                    @click="$emit('view-document', row.booking_id)"
                  >
                    View Receipt
                  </button>
                  <button
                    v-else-if="row.invoice_available"
                    type="button"
                    class="ml-btn-ghost text-sm font-semibold"
                    @click="$emit('view-document', row.booking_id)"
                  >
                    View Invoice
                  </button>
                  <button
                    v-else
                    type="button"
                    class="ml-btn-ghost text-sm font-semibold opacity-50 cursor-not-allowed"
                    disabled
                  >
                    No Receipt
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredRecords.length > VISIBLE_LIST_LIMIT" class="mt-4 flex justify-center">
          <button
            type="button"
            class="ml-btn-ghost text-sm font-semibold"
            @click="receiptsExpanded = !receiptsExpanded"
          >
            {{ receiptsExpanded ? 'Show Less' : `View All Payment Records (${filteredRecords.length})` }}
          </button>
        </div>
      </template>
    </template>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { filterTabClass } from '../utils/bookingDisplay';

const columns = ['Event', 'Date', 'Booth', 'Amount', 'Status', 'Action'];
const VISIBLE_LIST_LIMIT = 5;

const RECEIPT_FILTER_TABS = [
  { id: 'all', label: 'All' },
  { id: 'paid', label: 'Paid' },
  { id: 'unpaid', label: 'Unpaid' },
  { id: 'not_issued', label: 'Not Issued' },
  { id: 'cancelled', label: 'Cancelled' },
  { id: 'rejected', label: 'Rejected' },
];

const props = defineProps({
  records: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  loadError: { type: Boolean, default: false },
});

defineEmits(['retry', 'view-document']);

const receiptsSearchQuery = ref('');
const selectedReceiptStatus = ref('all');
const receiptsExpanded = ref(false);

const normalizeSearch = (value) => String(value ?? '').toLowerCase().trim();

const formatAmount = (value) => Number(value ?? 0).toFixed(2);

const formatRecordDate = (dateStr) => {
  if (!dateStr) return '—';
  const date = new Date(`${dateStr}T00:00:00`);
  if (Number.isNaN(date.getTime())) return dateStr;
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

const displayStatus = (row) => {
  if (['Cancelled', 'Rejected'].includes(row.booking_status)) {
    return row.booking_status;
  }
  if (row.payment_status === 'Unpaid') return 'Unpaid';
  return row.payment_status;
};

const matchesReceiptStatusFilter = (row, filterId) => {
  if (filterId === 'all') return true;
  if (filterId === 'cancelled') return row.booking_status === 'Cancelled';
  if (filterId === 'rejected') return row.booking_status === 'Rejected';
  if (filterId === 'paid') return row.payment_status === 'Paid';
  if (filterId === 'unpaid') return row.payment_status === 'Unpaid';
  if (filterId === 'not_issued') return row.payment_status === 'Not Issued';
  return true;
};

const recordMatchesSearch = (row, query) => {
  if (!query) return true;

  const haystack = [
    row.event,
    row.date,
    formatRecordDate(row.date),
    row.booth_number,
    row.amount,
    formatAmount(row.amount),
    `RM ${formatAmount(row.amount)}`,
    row.payment_status,
    row.booking_status,
    displayStatus(row),
    row.booking_id,
    `#${row.booking_id}`,
  ]
    .filter((part) => part != null && part !== '')
    .join(' ')
    .toLowerCase();

  return haystack.includes(query);
};

const filteredRecords = computed(() => {
  const query = normalizeSearch(receiptsSearchQuery.value);

  return props.records.filter(
    (row) =>
      matchesReceiptStatusFilter(row, selectedReceiptStatus.value) &&
      recordMatchesSearch(row, query),
  );
});

const visibleRecords = computed(() =>
  receiptsExpanded.value
    ? filteredRecords.value
    : filteredRecords.value.slice(0, VISIBLE_LIST_LIMIT),
);

const receiptFilterCounts = computed(() =>
  RECEIPT_FILTER_TABS.reduce((counts, tab) => {
    counts[tab.id] = props.records.filter((row) => matchesReceiptStatusFilter(row, tab.id)).length;
    return counts;
  }, {}),
);

watch([receiptsSearchQuery, selectedReceiptStatus], () => {
  receiptsExpanded.value = false;
});

const statusBadgeClass = (row) => {
  const status = displayStatus(row);
  return {
    Paid: 'ml-badge bg-emerald-100 text-emerald-800',
    Unpaid: 'ml-badge bg-amber-100 text-amber-800',
    Pending: 'ml-badge bg-brand-100 text-brand-800',
    'Not Issued': 'ml-badge bg-ink-100 text-ink-700',
    Cancelled: 'ml-badge bg-ink-100 text-ink-700',
    Rejected: 'ml-badge bg-rose-100 text-rose-800',
  }[status] || 'ml-badge bg-ink-100 text-ink-700';
};
</script>
