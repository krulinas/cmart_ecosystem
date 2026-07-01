<template>
  <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5 space-y-10">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div class="min-w-0">
        <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Vendor Portal</span>
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-ink-900 tracking-tight">Analytics &amp; Reports</h2>
          <span
            class="ml-badge bg-sky-100 text-sky-800 inline-flex items-center gap-1"
            title="These insights only use your own vendor records, not overall CMart performance."
          >
            Vendor-only data
            <InfoHelpTip
              aria-label="About vendor-only data"
              text-en="These insights only use your own vendor records, not overall CMart performance."
              text-ms="Data ini hanya berdasarkan rekod vendor anda sendiri, bukan prestasi keseluruhan CMart."
              placement="bottom-right"
            />
          </span>
        </div>
        <p class="mt-2 text-base text-ink-500 max-w-2xl leading-relaxed">
          Personal insights from your bookings, payments, reuse listings, and booth activity.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <button type="button" class="ml-btn-ghost" :disabled="loading || exporting" @click="$emit('retry')">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
        <button type="button" class="ml-btn-primary" :disabled="loading || exporting || loadError" @click="exportReport('csv')">
          {{ exporting ? 'Exporting…' : 'Export CSV' }}
        </button>
        <button type="button" class="ml-btn-ghost" :disabled="loading || exporting || loadError" @click="exportReport('json')">
          Download JSON
        </button>
      </div>
    </div>

    <div class="rounded-2xl border border-sky-100 bg-sky-50/60 px-5 py-5 sm:px-6 sm:py-5">
      <BilingualHelpText
        text-en="This dashboard shows your own vendor activity at CMart, including booth bookings, verified payments, reuse listings, profile readiness, and current booth status. Use it to understand your progress and prepare for upcoming carboot events."
        text-ms="Dashboard ini menunjukkan aktiviti vendor anda sendiri di CMart, termasuk tempahan tapak, bayaran yang telah disahkan, listing reuse, kelengkapan profil, dan status tapak semasa. Ia membantu vendor memahami perkembangan dan bersedia untuk event carboot akan datang."
        en-class="text-readable-base text-sky-900"
        ms-class="text-sm text-sky-700/80 mt-1.5"
      />
    </div>

    <details class="rounded-2xl border border-ink-100 bg-white/70 px-5 py-4 sm:px-6">
      <summary class="cursor-pointer text-base font-bold text-ink-800 select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-lg">
        How to read this dashboard
      </summary>
      <ul class="mt-4 space-y-3 text-base text-ink-600 list-disc pl-5 leading-relaxed">
        <li v-for="item in dashboardGuideItems" :key="item.en">
          <span>{{ item.en }}</span>
          <span class="block text-sm text-ink-400 mt-1">{{ item.ms }}</span>
        </li>
      </ul>
    </details>

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
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        <div
          v-for="card in insightCards"
          :key="card.key"
          class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7 shadow-sm"
        >
          <div class="flex h-11 w-11 items-center justify-center rounded-xl mb-4 border" :class="card.iconClass">
            <component :is="card.icon" />
          </div>
          <p class="text-3xl font-black text-ink-900 tabular-nums leading-none">{{ card.displayValue }}</p>
          <div class="mt-3 flex items-center gap-1.5">
            <p class="text-base font-bold text-ink-500 uppercase tracking-wide">{{ card.label }}</p>
            <InfoHelpTip
              :aria-label="`About ${card.label}`"
              :text-en="card.helpEn"
              :text-ms="card.helpMs"
            />
          </div>
          <p v-if="card.subtext" class="mt-2 text-sm text-ink-400">{{ card.subtext }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <div class="flex items-center gap-1.5">
              <h3 class="text-xl font-extrabold text-ink-900">Profile Completion</h3>
              <InfoHelpTip
                aria-label="About Profile Completion"
                text-en="How complete your business profile is. A complete profile improves vendor trust and booth visibility."
                text-ms="Menunjukkan tahap kelengkapan profil bisnes. Profil lengkap membantu meningkatkan kepercayaan dan visibility vendor."
              />
            </div>
            <p class="text-base text-ink-500 leading-relaxed">Complete your business profile to improve booth visibility.</p>
          </div>
          <button type="button" class="ml-btn-ghost shrink-0" @click="$emit('edit-profile')">
            Edit Profile
          </button>
        </div>
        <div class="mt-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-base font-semibold text-ink-700 mb-3">
            <span>{{ summary.profile_completion_percent ?? 0 }}% complete</span>
            <span v-if="missingProfileFields.length" class="text-ink-500 font-normal">
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

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
          <h3 class="text-xl font-extrabold text-ink-900 mb-1">Monthly Bookings</h3>
          <p class="text-sm text-ink-500 mb-3">Last 6 months by event date</p>
          <BilingualHelpText
            class="mb-5"
            text-en="Shows how many booth bookings are linked to event dates each month. A line chart is used to make booking trends easier to see over time."
            text-ms="Menunjukkan jumlah tempahan tapak mengikut bulan acara. Carta line digunakan supaya trend naik turun tempahan lebih mudah dilihat."
          />
          <div v-if="hasBookingTrend" class="relative h-64">
            <canvas ref="bookingTrendCanvas"></canvas>
          </div>
          <p v-else class="text-base text-ink-500 py-10 text-center">No booking trend data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
          <h3 class="text-xl font-extrabold text-ink-900 mb-1">Monthly Payments</h3>
          <p class="text-sm text-ink-500 mb-3">Paid booth invoices over the last 6 months</p>
          <BilingualHelpText
            class="mb-5"
            text-en="Shows the total verified booth payments received each month. A bar chart is used because monthly totals are easier to compare side by side."
            text-ms="Menunjukkan jumlah bayaran tapak yang telah disahkan setiap bulan. Carta bar digunakan supaya jumlah bayaran antara bulan mudah dibandingkan."
          />
          <div v-if="hasPaymentTrend" class="relative h-64">
            <canvas ref="paymentTrendCanvas"></canvas>
          </div>
          <p v-else class="text-base text-ink-500 py-10 text-center">No payment trend data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
          <h3 class="text-xl font-extrabold text-ink-900 mb-1">Booking Status</h3>
          <BilingualHelpText
            class="mb-5"
            text-en="Shows how your bookings are distributed by status, such as approved, pending, rejected, revision, or withdrawn. A donut chart shows each status as part of the total."
            text-ms="Menunjukkan pecahan status tempahan seperti approved, pending, rejected, revision, atau withdrawn. Carta donut menunjukkan setiap status sebagai sebahagian daripada jumlah keseluruhan."
          />
          <div v-if="hasBookingStatusChart" class="relative h-64">
            <canvas ref="bookingStatusCanvas"></canvas>
          </div>
          <p v-else class="text-base text-ink-500 py-10 text-center">No booking status data yet.</p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
          <h3 class="text-xl font-extrabold text-ink-900 mb-1">Reuse Listing Status</h3>
          <BilingualHelpText
            class="mb-5"
            text-en="Shows the status of your reuse item listings once items are added."
            text-ms="Menunjukkan status listing barang reuse selepas item ditambah."
          />
          <div v-if="hasReuseStatusChart" class="relative h-64">
            <canvas ref="reuseStatusCanvas"></canvas>
          </div>
          <div v-else class="py-8 text-center">
            <BilingualHelpText
              text-en="No reuse listing data yet. Add reuse items to see listing status insights here."
              text-ms="Belum ada data listing reuse. Tambah barang reuse untuk melihat insight status listing di sini."
              en-class="text-base text-ink-500"
              ms-class="text-sm text-ink-400 mt-1.5"
            />
            <button type="button" class="mt-5 ml-btn-ghost font-semibold" @click="$emit('manage-reuse')">
              Manage Reuse Listings
            </button>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7">
        <h3 class="text-xl font-extrabold text-ink-900 mb-1">Recent Activity</h3>
        <BilingualHelpText
          class="mb-5"
          text-en="Recent Activity lists your latest booking and payment updates so you can quickly track what changed."
          text-ms="Recent Activity menunjukkan kemas kini tempahan dan bayaran terkini supaya vendor mudah tahu perubahan terbaru."
        />
        <div v-if="!recentActivity.length" class="text-base text-ink-500 py-6 text-center leading-relaxed">
          No recent vendor activity yet. Bookings, payments, and listings will appear here.
        </div>
        <ul v-else class="divide-y divide-ink-100">
          <li
            v-for="(activity, index) in visibleActivity"
            :key="`${activity.type}-${activity.occurred_at}-${index}`"
            class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span :class="activityBadgeClass(activity.type)">{{ activityTypeLabel(activity.type) }}</span>
                <p class="font-semibold text-base text-ink-900 truncate">{{ activity.title }}</p>
              </div>
              <p class="text-sm text-ink-500 mt-1">{{ formatDateTime(activity.occurred_at) }}</p>
            </div>
            <div class="text-base font-semibold text-ink-700 shrink-0">
              <span v-if="activity.amount != null">RM {{ Number(activity.amount).toFixed(2) }}</span>
              <span v-if="activity.status" class="ml-badge bg-ink-100 text-ink-700 capitalize ml-0 sm:ml-2">
                {{ activity.status }}
              </span>
            </div>
          </li>
        </ul>
        <div v-if="recentActivity.length > activityLimit" class="mt-4 flex justify-center">
          <button type="button" class="ml-btn-ghost font-semibold" @click="activityExpanded = !activityExpanded">
            {{ activityExpanded ? 'Show Less' : `View All Activity (${recentActivity.length})` }}
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-brand-100 bg-brand-50/40 p-6 sm:p-7">
        <h3 class="text-base font-bold uppercase tracking-wider text-brand-700 mb-1">Current Booth Snapshot</h3>
        <BilingualHelpText
          class="mb-5"
          text-en="Shows the latest approved booth information for your upcoming or current event."
          text-ms="Menunjukkan maklumat tapak terkini yang telah diluluskan untuk event semasa atau akan datang."
          en-class="text-readable-sm text-brand-800/90"
          ms-class="text-sm text-brand-700/75"
        />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-base">
          <div>
            <p class="text-sm text-ink-500">Booth Status</p>
            <p class="font-bold text-ink-900 mt-1.5">{{ booth.booth_status || 'No Active Booking' }}</p>
          </div>
          <div>
            <p class="text-sm text-ink-500">Current Event</p>
            <p class="font-bold text-ink-900 mt-1.5">{{ booth.current_event || '—' }}</p>
          </div>
          <div>
            <p class="text-sm text-ink-500">Booth Number</p>
            <p class="font-bold text-ink-900 mt-1.5">{{ booth.booth_number || '—' }}</p>
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
import InfoHelpTip from './InfoHelpTip.vue';
import BilingualHelpText from './BilingualHelpText.vue';

const props = defineProps({
  analytics: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  loadError: { type: Boolean, default: false },
});

defineEmits(['retry', 'edit-profile', 'manage-reuse']);

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

const PROFILE_FIELD_LABELS = {
  business_name: 'business name',
  business_phone: 'business phone',
  business_category: 'business category',
  description: 'description',
  logo_path: 'business logo',
};

const dashboardGuideItems = [
  { en: 'Cards show quick totals.', ms: 'Kad menunjukkan ringkasan nombor penting.' },
  { en: 'Line chart shows booking trend over time.', ms: 'Carta line menunjukkan trend tempahan mengikut masa.' },
  { en: 'Bar chart compares monthly payment totals.', ms: 'Carta bar membandingkan jumlah bayaran bulanan.' },
  { en: 'Donut chart shows booking status distribution.', ms: 'Carta donut menunjukkan pecahan status tempahan.' },
  { en: 'Recent Activity shows the latest booking/payment updates.', ms: 'Recent Activity menunjukkan kemas kini tempahan/bayaran terkini.' },
  { en: 'Current Booth Snapshot shows your latest approved booth assignment.', ms: 'Current Booth Snapshot menunjukkan maklumat tapak terkini yang telah diluluskan.' },
];

const summary = computed(() => props.analytics?.summary || {});
const booth = computed(() => props.analytics?.booth || {});
const trends = computed(() => props.analytics?.trends || { monthly_bookings: [], monthly_payments: [] });
const distributions = computed(() => props.analytics?.distributions || { booking_status: {}, reuse_listing_status: {} });
const recentActivity = computed(() => props.analytics?.recent_activity || []);

const visibleActivity = computed(() =>
  activityExpanded.value ? recentActivity.value : recentActivity.value.slice(0, activityLimit),
);

const missingProfileFields = computed(() =>
  (summary.value.profile_missing_fields || []).map(
    (field) => PROFILE_FIELD_LABELS[field] || field.replace(/_/g, ' '),
  ),
);

const formatCount = (value) => new Intl.NumberFormat('en-MY').format(value ?? 0);
const formatCurrency = (value) =>
  new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value ?? 0);

const icon = (path) => () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: path }),
  ]);

const insightCards = computed(() => [
  {
    key: 'bookings',
    label: 'Total Bookings',
    displayValue: formatCount(summary.value.total_bookings),
    subtext: `${summary.value.upcoming_bookings ?? 0} upcoming`,
    helpEn: 'All booth booking requests created by this vendor.',
    helpMs: 'Jumlah semua permohonan tempahan tapak oleh vendor ini.',
    icon: icon('M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'),
    iconClass: 'bg-brand-50 text-brand-600 border-brand-100',
  },
  {
    key: 'upcoming',
    label: 'Upcoming Bookings',
    displayValue: formatCount(summary.value.upcoming_bookings),
    subtext: `${summary.value.completed_bookings ?? 0} completed`,
    helpEn: 'Bookings for upcoming events that have not happened yet.',
    helpMs: 'Tempahan untuk event yang belum berlangsung.',
    icon: icon('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'),
    iconClass: 'bg-sky-50 text-sky-700 border-sky-100',
  },
  {
    key: 'receipts',
    label: 'Booking Receipts',
    displayValue: formatCount(summary.value.total_receipts),
    subtext: 'Issued payment records',
    helpEn: 'Payment receipt records issued for this vendor\'s booth bookings.',
    helpMs: 'Rekod resit bayaran untuk tempahan tapak vendor ini.',
    icon: icon('M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'),
    iconClass: 'bg-violet-50 text-violet-700 border-violet-100',
  },
  {
    key: 'paid',
    label: 'Total Paid',
    displayValue: formatCurrency(summary.value.total_paid_amount),
    subtext: 'Booth payment total',
    helpEn: 'Total verified booth payments.',
    helpMs: 'Jumlah bayaran tapak yang telah disahkan.',
    icon: icon('M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
    iconClass: 'bg-emerald-50 text-emerald-700 border-emerald-100',
  },
  {
    key: 'reuse',
    label: 'Active Reuse Listings',
    displayValue: formatCount(summary.value.active_reuse_listings),
    subtext: `${summary.value.total_reuse_listings ?? 0} total listings`,
    helpEn: 'Reuse items currently active or visible in the vendor preview.',
    helpMs: 'Barang reuse yang sedang aktif atau dipaparkan dalam preview vendor.',
    icon: icon('M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'),
    iconClass: 'bg-amber-50 text-amber-700 border-amber-100',
  },
  {
    key: 'profile',
    label: 'Profile Completion',
    displayValue: `${summary.value.profile_completion_percent ?? 0}%`,
    subtext: missingProfileFields.value.length ? 'Fields still missing' : 'Profile complete',
    helpEn: 'How complete your business profile is. A complete profile improves vendor trust and booth visibility.',
    helpMs: 'Menunjukkan tahap kelengkapan profil bisnes. Profil lengkap membantu meningkatkan kepercayaan dan visibility vendor.',
    icon: icon('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'),
    iconClass: 'bg-ink-50 text-ink-700 border-ink-100',
  },
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

const CHART_TICK_FONT = { size: 13 };
const CHART_LEGEND_FONT = { size: 13 };

const axisChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { font: CHART_TICK_FONT, maxRotation: 0 } },
    y: { ticks: { font: CHART_TICK_FONT }, beginAtZero: true },
  },
};

const donutChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: { font: CHART_LEGEND_FONT, padding: 16, boxWidth: 14 },
    },
  },
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
      options: axisChartOptions,
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
      options: axisChartOptions,
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
      options: donutChartOptions,
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
      options: donutChartOptions,
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
