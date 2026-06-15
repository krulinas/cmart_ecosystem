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

    <div v-else class="overflow-x-auto rounded-2xl border border-ink-100">
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
            v-for="row in records"
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
  </section>
</template>

<script setup>
const columns = ['Event', 'Date', 'Booth', 'Amount', 'Status', 'Action'];

defineProps({
  records: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  loadError: { type: Boolean, default: false },
});

defineEmits(['retry', 'view-document']);

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
