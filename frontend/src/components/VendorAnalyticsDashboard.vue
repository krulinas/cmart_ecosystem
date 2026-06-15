<template>
  <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Vendor Portal</span>
        <h2 class="text-xl sm:text-2xl font-extrabold text-ink-900 tracking-tight">Analytics &amp; Reports</h2>
        <p class="mt-1 text-sm text-ink-500 max-w-2xl">
          Personal insights from your bookings, payments, reuse listings, and booth activity.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="ml-btn-ghost text-sm" :disabled="loading || exporting" @click="$emit('retry')">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
        <button type="button" class="ml-btn-primary text-sm" :disabled="loading || exporting || loadError" @click="exportReport('csv')">
          {{ exporting ? 'Exporting…' : 'Export CSV' }}
        </button>
        <button type="button" class="ml-btn-ghost text-sm" :disabled="loading || exporting || loadError" @click="exportReport('json')">
          Download JSON
        </button>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="n in 6" :key="n" class="rounded-2xl border border-ink-100 bg-white/70 p-6 animate-pulse">
        <div class="h-10 w-10 bg-brand-100 rounded-xl mb-4"></div>
        <div class="h-8 w-24 bg-ink-200 rounded mb-2"></div>
        <div class="h-4 w-32 bg-ink-100 rounded"></div>
      </div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load your analytics right now.</p>
      <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="$emit('retry')">Try Again</button>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="card in insightCards"
          :key="card.key"
          class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6 shadow-sm"
        >
          <div class="flex h-10 w-10 items-center justify-center rounded-xl mb-3 border" :class="card.iconClass">
            <component :is="card.icon" />
          </div>
          <p class="text-2xl font-black text-ink-900 tabular-nums leading-none">{{ card.displayValue }}</p>
          <p class="mt-2 text-sm font-bold text-ink-500 uppercase tracking-wide">{{ card.label }}</p>
          <p v-if="card.subtext" class="mt-1 text-xs text-ink-400">{{ card.subtext }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 class="text-lg font-extrabold text-ink-900">Profile Completion</h3>
            <p class="text-sm text-ink-500">Complete your business profile to improve booth visibility.</p>
          </div>
          <button type="button" class="ml-btn-ghost text-sm shrink-0" @click="$emit('edit-profile')">
            Edit Profile
          </button>
        </div>
        <div class="mt-4">
          <div class="flex items-center justify-between text-sm font-semibold text-ink-700 mb-2">
            <span>{{ summary.profile_completion_percent ?? 0 }}% complete</span>
            <span v-if="missingProfileFields.length" class="text-ink-500">
              Missing: {{ missingProfileFields.join(', ') }}
            </span>
            <span v-else class="text-emerald-700">All set</span>
          </div>
          <div class="h-3 rounded-full bg-ink-100 overflow-hidden">
            <div
              class="h-full rounded-full bg-brand-500 transition-all duration-500"
              :style="{ width: `${summary.profile_completion_percent ?? 0}%` }"
            ></div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Monthly Bookings</h3>
          <p class="text-xs text-ink-500 mb-4">Last 6 months by event date</p>
          <div v-if="hasBookingTrend" class="relative h-56">
            <canvas ref="bookingTrendCanvas"></canvas>
          </div>
          <p v-else class="text-sm text-ink-500 py-10 text-center">No booking trend data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Monthly Payments</h3>
          <p class="text-xs text-ink-500 mb-4">Paid booth invoices over the last 6 months</p>
          <div v-if="hasPaymentTrend" class="relative h-56">
            <canvas ref="paymentTrendCanvas"></canvas>
          </div>
          <p v-else class="text-sm text-ink-500 py-10 text-center">No payment trend data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Booking Status</h3>
          <div v-if="hasBookingStatusChart" class="relative h-56">
            <canvas ref="bookingStatusCanvas"></canvas>
          </div>
          <p v-else class="text-sm text-ink-500 py-10 text-center">No booking status data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Reuse Listing Status</h3>
          <div v-if="hasReuseStatusChart" class="relative h-56">
            <canvas ref="reuseStatusCanvas"></canvas>
          </div>
          <p v-else class="text-sm text-ink-500 py-10 text-center">No reuse listing data yet.</p>
        </div>
      </div>

      <div class="rounded-2xl border border-ink-100 bg-white/70 p-5 sm:p-6">
        <h3 class="text-lg font-extrabold text-ink-900 mb-4">Recent Activity</h3>
        <div v-if="!recentActivity.length" class="text-sm text-ink-500 py-6 text-center">
          No recent vendor activity yet. Bookings, payments, and listings will appear here.
        </div>
        <ul v-else class="divide-y divide-ink-100">
          <li
            v-for="(activity, index) in visibleActivity"
            :key="`${activity.type}-${activity.occurred_at}-${index}`"
            class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span :class="activityBadgeClass(activity.type)">{{ activityTypeLabel(activity.type) }}</span>
                <p class="font-semibold text-ink-900 truncate">{{ activity.title }}</p>
              </div>
              <p class="text-xs text-ink-500 mt-1">{{ formatDateTime(activity.occurred_at) }}</p>
            </div>
            <div class="text-sm font-semibold text-ink-700 shrink-0">
              <span v-if="activity.amount != null">RM {{ Number(activity.amount).toFixed(2) }}</span>
              <span v-if="activity.status" class="ml-badge bg-ink-100 text-ink-700 capitalize ml-0 sm:ml-2">
                {{ activity.status }}
              </span>
            </div>
          </li>
        </ul>
        <div v-if="recentActivity.length > activityLimit" class="mt-4 flex justify-center">
          <button type="button" class="ml-btn-ghost text-sm font-semibold" @click="activityExpanded = !activityExpanded">
            {{ activityExpanded ? 'Show Less' : `View All Activity (${recentActivity.length})` }}
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-brand-100 bg-brand-50/40 p-5 sm:p-6">
        <h3 class="text-sm font-bold uppercase tracking-wider text-brand-700 mb-3">Current Booth Snapshot</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
          <div>
            <p class="text-ink-500">Booth Status</p>
            <p class="font-bold text-ink-900 mt-1">{{ booth.booth_status || 'No Active Booking' }}</p>
          </div>
          <div>
            <p class="text-ink-500">Current Event</p>
            <p class="font-bold text-ink-900 mt-1">{{ booth.current_event || '—' }}</p>
          </div>
          <div>
            <p class="text-ink-500">Booth Number</p>
            <p class="font-bold text-ink-900 mt-1">{{ booth.booth_number || '—' }}</p>
          </div>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, h, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';
import api from '../services/api';
import { extractApiError } from '../utils/apiErrors';
import { downloadVendorReportCsv, downloadVendorReportJson } from '../utils/vendorReport';

const props = defineProps({
  analytics: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  loadError: { type: Boolean, default: false },
});

defineEmits(['retry', 'edit-profile']);

const toast = useToast();
const activityLimit = 5;
const activityExpanded = ref(false);
const exporting = ref(false);

const bookingTrendCanvas = ref(null);
const paymentTrendCanvas = ref(null);
const bookingStatusCanvas = ref(null);
const reuseStatusCanvas = ref(null);

let bookingTrendChart = null;
let paymentTrendChart = null;
let bookingStatusChart = null;
let reuseStatusChart = null;

const summary = computed(() => props.analytics?.summary || {});
const booth = computed(() => props.analytics?.booth || {});
const trends = computed(() => props.analytics?.trends || { monthly_bookings: [], monthly_payments: [] });
const distributions = computed(() => props.analytics?.distributions || { booking_status: {}, reuse_listing_status: {} });
const recentActivity = computed(() => props.analytics?.recent_activity || []);

const visibleActivity = computed(() =>
  activityExpanded.value ? recentActivity.value : recentActivity.value.slice(0, activityLimit),
);

const missingProfileFields = computed(() =>
  (summary.value.profile_missing_fields || []).map((field) => field.replace(/_/g, ' ')),
);

const formatCount = (value) => new Intl.NumberFormat('en-MY').format(value ?? 0);
const formatCurrency = (value) =>
  new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value ?? 0);

const icon = (path) => () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: path }),
  ]);

const insightCards = computed(() => [
  { key: 'bookings', label: 'Total Bookings', displayValue: formatCount(summary.value.total_bookings), subtext: `${summary.value.upcoming_bookings ?? 0} upcoming`, icon: icon('M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'), iconClass: 'bg-brand-50 text-brand-600 border-brand-100' },
  { key: 'upcoming', label: 'Upcoming Bookings', displayValue: formatCount(summary.value.upcoming_bookings), subtext: `${summary.value.completed_bookings ?? 0} completed`, icon: icon('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'), iconClass: 'bg-sky-50 text-sky-700 border-sky-100' },
  { key: 'receipts', label: 'Booking Receipts', displayValue: formatCount(summary.value.total_receipts), subtext: 'Issued payment records', icon: icon('M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'), iconClass: 'bg-violet-50 text-violet-700 border-violet-100' },
  { key: 'paid', label: 'Total Paid', displayValue: formatCurrency(summary.value.total_paid_amount), subtext: 'Booth payment total', icon: icon('M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'), iconClass: 'bg-emerald-50 text-emerald-700 border-emerald-100' },
  { key: 'reuse', label: 'Active Reuse Listings', displayValue: formatCount(summary.value.active_reuse_listings), subtext: `${summary.value.total_reuse_listings ?? 0} total listings`, icon: icon('M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'), iconClass: 'bg-amber-50 text-amber-700 border-amber-100' },
  { key: 'profile', label: 'Profile Completion', displayValue: `${summary.value.profile_completion_percent ?? 0}%`, subtext: missingProfileFields.value.length ? 'Fields still missing' : 'Profile complete', icon: icon('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'), iconClass: 'bg-ink-50 text-ink-700 border-ink-100' },
]);

const hasBookingTrend = computed(() => (trends.value.monthly_bookings || []).some((row) => row.count > 0));
const hasPaymentTrend = computed(() => (trends.value.monthly_payments || []).some((row) => row.amount > 0));
const hasBookingStatusChart = computed(() => Object.keys(distributions.value.booking_status || {}).length > 0);
const hasReuseStatusChart = computed(() => (summary.value.total_reuse_listings ?? 0) > 0);

const activityTypeLabel = (type) => ({
  booking: 'Booking',
  payment: 'Payment',
  reuse_item: 'Reuse Item',
  profile: 'Profile',
}[type] || 'Activity');

const activityBadgeClass = (type) => ({
  booking: 'ml-badge bg-brand-100 text-brand-800',
  payment: 'ml-badge bg-emerald-100 text-emerald-800',
  reuse_item: 'ml-badge bg-amber-100 text-amber-800',
  profile: 'ml-badge bg-ink-100 text-ink-700',
}[type] || 'ml-badge bg-ink-100 text-ink-700');

const formatDateTime = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const destroyCharts = () => {
  [bookingTrendChart, paymentTrendChart, bookingStatusChart, reuseStatusChart].forEach((chart) => chart?.destroy());
  bookingTrendChart = paymentTrendChart = bookingStatusChart = reuseStatusChart = null;
};

const renderCharts = () => {
  destroyCharts();
  if (props.loading || props.loadError) return;

  const bookingMonths = trends.value.monthly_bookings || [];
  if (bookingTrendCanvas.value && hasBookingTrend.value) {
    bookingTrendChart = new Chart(bookingTrendCanvas.value, {
      type: 'line',
      data: {
        labels: bookingMonths.map((row) => row.label),
        datasets: [{ label: 'Bookings', data: bookingMonths.map((row) => row.count), borderColor: '#ea580c', backgroundColor: 'rgba(234,88,12,0.15)', tension: 0.3, fill: true }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    });
  }

  const paymentMonths = trends.value.monthly_payments || [];
  if (paymentTrendCanvas.value && hasPaymentTrend.value) {
    paymentTrendChart = new Chart(paymentTrendCanvas.value, {
      type: 'bar',
      data: {
        labels: paymentMonths.map((row) => row.label),
        datasets: [{ label: 'Paid (RM)', data: paymentMonths.map((row) => row.amount), backgroundColor: '#10b981' }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    });
  }

  const bookingStatus = distributions.value.booking_status || {};
  const statusLabels = Object.keys(bookingStatus);
  if (bookingStatusCanvas.value && statusLabels.length) {
    bookingStatusChart = new Chart(bookingStatusCanvas.value, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{ data: Object.values(bookingStatus), backgroundColor: ['#ea580c', '#8b5cf6', '#10b981', '#f59e0b', '#f43f5e', '#64748b'], borderWidth: 0 }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    });
  }

  const reuseStatus = distributions.value.reuse_listing_status || {};
  if (reuseStatusCanvas.value && hasReuseStatusChart.value) {
    reuseStatusChart = new Chart(reuseStatusCanvas.value, {
      type: 'doughnut',
      data: {
        labels: ['Active', 'Inactive'],
        datasets: [{ data: [reuseStatus.active || 0, reuseStatus.inactive || 0], backgroundColor: ['#10b981', '#94a3b8'], borderWidth: 0 }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    });
  }
};

const exportReport = async (format) => {
  exporting.value = true;
  try {
    const { data } = await api.get('/vendor/analytics/report');
    if (format === 'csv') {
      downloadVendorReportCsv(data);
      toast.success('CSV report downloaded.');
    } else {
      downloadVendorReportJson(data);
      toast.success('JSON report downloaded.');
    }
  } catch (error) {
    console.error('Unable to export vendor report:', error);
    toast.error(extractApiError(error));
  } finally {
    exporting.value = false;
  }
};

watch(
  () => [props.analytics, props.loading, props.loadError],
  async () => {
    if (!props.loading && !props.loadError) {
      await nextTick();
      renderCharts();
    }
  },
  { deep: true },
);

onBeforeUnmount(destroyCharts);
</script>
