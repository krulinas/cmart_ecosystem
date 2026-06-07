<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50 py-10 px-4 sm:px-6">
    <div class="max-w-6xl mx-auto space-y-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <router-link to="/" class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 transition">
          <span class="mr-1">←</span> Back to Carboot@CMart
        </router-link>
        <router-link
          to="/vendor-booking"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600 transition"
        >
          Book a Space
        </router-link>
      </div>

      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <span class="ml-badge bg-brand-100 text-brand-700">Vendor Hub</span>
        <h1 class="mt-2 text-3xl font-black text-ink-900 tracking-tight">My Dashboard</h1>
        <p class="mt-1 text-sm text-ink-500">
          Welcome back, {{ userDisplayName }}. Track approvals, booth details, and your vendor history in one place.
        </p>
      </header>

      <!-- Top Row: Status Tracker -->
      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
          <div>
            <h2 class="text-xl font-extrabold text-ink-900">My Bookings</h2>
            <p class="text-sm text-ink-500">Track your booking through the Carboot@CMart approval pipeline.</p>
          </div>
          <button class="ml-btn-ghost" @click="fetchMyBookings" :disabled="loadingBookings">
            {{ loadingBookings ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>

        <div v-if="!myBookings.length" class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500">
          No booking records are currently available.
          <router-link to="/vendor-booking" class="mt-3 block text-brand-600 font-semibold hover:text-brand-700">
            Submit your first booking →
          </router-link>
        </div>

        <div v-else class="space-y-5">
          <article
            v-for="booking in myBookings"
            :key="booking.id"
            class="rounded-2xl border border-ink-200/80 bg-white/90 backdrop-blur-sm p-5 sm:p-6 shadow-sm"
          >
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-lg font-extrabold text-ink-900">Booking #{{ booking.id }}</h3>
                  <span :class="statusBadgeClass(booking.approval_status)">
                    {{ statusLabel(booking.approval_status) }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-ink-500">
                  {{ booking.space?.space_size || `Space ${booking.space_id}` }}
                  · {{ booking.product_category || 'Others' }}
                  · {{ booking.booking_date }}
                </p>
              </div>

              <button class="ml-btn-ghost shrink-0" @click="viewPdf(booking.id)">
                View PDF
              </button>
            </div>

            <div class="mt-6">
              <div class="relative">
                <div class="absolute left-0 right-0 top-4 h-1 rounded-full bg-ink-200"></div>
                <div
                  class="absolute left-0 top-4 h-1 rounded-full transition-all duration-700"
                  :class="progressBarClass(booking.approval_status)"
                  :style="{ width: progressWidth(booking.approval_status) }"
                ></div>

                <div class="relative grid grid-cols-3 gap-2">
                  <div
                    v-for="step in pipelineSteps"
                    :key="step.status"
                    class="flex flex-col items-center text-center"
                  >
                    <div
                      class="h-9 w-9 rounded-full border-4 bg-white flex items-center justify-center text-xs font-extrabold transition-all duration-500"
                      :class="stepClass(booking.approval_status, step.status)"
                    >
                      {{ step.index }}
                    </div>
                    <div class="mt-2 text-xs font-semibold text-ink-700">{{ step.label }}</div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="booking.approval_status === 'Needs_Revision'"
              class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-4"
            >
              <h4 class="font-bold text-brand-900">Revision Required</h4>
              <p class="mt-1 text-sm text-brand-800">
                {{ booking.revision_comment || '422 Unprocessable Entity: A revision comment was not provided by the reviewer.' }}
              </p>
              <div class="mt-4 flex flex-col sm:flex-row gap-3">
                <input
                  v-model="resubmitDates[booking.id]"
                  type="date"
                  class="ml-input sm:max-w-xs"
                  :placeholder="booking.booking_date"
                />
                <button class="ml-btn-primary" @click="resubmitBooking(booking)">
                  Resubmit Booking
                </button>
              </div>
              <p class="mt-2 text-xs text-brand-700">
                Leave the date field blank to retain the existing booking date.
              </p>
            </div>
          </article>
        </div>
      </section>

      <!-- Middle Row: Booth + Business Profile -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
          <div v-if="hasApprovedBooking" class="space-y-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <span class="ml-badge bg-emerald-100 text-emerald-800">Approved</span>
                <h2 class="mt-2 text-xl font-extrabold text-ink-900">Booth Assignment &amp; QR Pass</h2>
                <p class="text-sm text-ink-500 mt-1">Your booth is confirmed. Present this pass at the event entrance.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-6 shadow-inner">
                <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Assigned Booth</p>
                <p class="mt-2 text-3xl font-black text-ink-900">{{ boothLabel }}</p>
                <p class="mt-2 text-sm text-ink-500">{{ latestApprovedBooking?.space?.space_size || 'Standard space' }}</p>
                <p class="mt-1 text-sm font-semibold text-ink-700">
                  Event date: {{ latestApprovedBooking?.booking_date || '—' }}
                </p>
              </div>

              <div class="flex flex-col items-center justify-center rounded-2xl border border-ink-200 bg-white p-6 shadow-sm">
                <div class="flex h-36 w-36 items-center justify-center rounded-2xl border-2 border-dashed border-ink-300 bg-ink-50">
                  <svg class="h-24 w-24 text-ink-800" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true">
                    <rect x="8" y="8" width="24" height="24" rx="2" />
                    <rect x="8" y="40" width="24" height="24" rx="2" />
                    <rect x="40" y="8" width="16" height="16" rx="2" />
                    <rect x="40" y="32" width="8" height="8" rx="1" />
                    <rect x="52" y="32" width="8" height="8" rx="1" />
                    <rect x="40" y="48" width="8" height="8" rx="1" />
                    <rect x="52" y="48" width="8" height="8" rx="1" />
                    <rect x="64" y="8" width="28" height="8" rx="2" />
                    <rect x="64" y="24" width="28" height="8" rx="2" />
                    <rect x="8" y="72" width="52" height="8" rx="2" />
                    <rect x="8" y="84" width="36" height="8" rx="2" />
                    <rect x="68" y="72" width="24" height="24" rx="2" />
                  </svg>
                </div>
                <p class="mt-4 text-xs font-bold uppercase tracking-wider text-ink-500">Vendor QR Pass</p>
                <p class="mt-1 text-sm font-semibold text-ink-700">Booking #{{ latestApprovedBooking?.id }}</p>
              </div>
            </div>
          </div>

          <div
            v-else
            class="relative overflow-hidden rounded-2xl border border-ink-200/80 bg-ink-50/60 p-8 sm:p-10 text-center"
          >
            <div class="absolute inset-0 bg-white/40 backdrop-blur-[2px]"></div>
            <div class="relative z-10 flex flex-col items-center">
              <div class="flex h-16 w-16 items-center justify-center rounded-full bg-ink-200/80 text-ink-500 shadow-inner">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <h2 class="mt-4 text-xl font-extrabold text-ink-900">Booth Assignment &amp; QR Pass</h2>
              <p class="mt-2 max-w-md text-sm text-ink-500">
                This section unlocks once your booking reaches <strong class="text-ink-700">Approved</strong> (Step 3) in the pipeline above.
              </p>
            </div>
          </div>
        </section>

        <aside class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
          <h2 class="text-xl font-extrabold text-ink-900">Business Profile</h2>
          <p class="text-sm text-ink-500 mt-1">Your registered vendor details on file.</p>

          <dl class="mt-6 space-y-5">
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Name</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ userDisplayName }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Contact</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ contactDisplay }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Registered Product</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ registeredProduct }}</dd>
              <p v-if="registeredCategory" class="mt-1 text-xs text-ink-500">{{ registeredCategory }}</p>
            </div>
          </dl>
        </aside>
      </div>

      <!-- Bottom Row: History & Receipts -->
      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
          <div>
            <h2 class="text-xl font-extrabold text-ink-900">History &amp; Receipts</h2>
            <p class="text-sm text-ink-500">Past events and payment records for your vendor account.</p>
          </div>
          <span class="ml-badge bg-ink-100 text-ink-600">Sample data</span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-ink-100">
          <table class="min-w-full divide-y divide-ink-100 text-sm">
            <thead class="bg-ink-50/80">
              <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Event</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Date</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Booth</th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">Amount</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 bg-white/60">
              <tr v-for="row in receiptHistory" :key="row.id" class="hover:bg-brand-50/40 transition-colors">
                <td class="px-4 py-4 font-semibold text-ink-900">{{ row.event }}</td>
                <td class="px-4 py-4 text-ink-600">{{ row.date }}</td>
                <td class="px-4 py-4 text-ink-600">{{ row.booth }}</td>
                <td class="px-4 py-4 text-right font-semibold text-ink-900">RM {{ row.amount.toFixed(2) }}</td>
                <td class="px-4 py-4">
                  <span :class="receiptStatusClass(row.status)">{{ row.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from './services/api';
import { useAuthStore } from './stores/auth';

const toast = useToast();
const auth = useAuthStore();

const myBookings = ref([]);
const loadingBookings = ref(false);
const resubmitDates = reactive({});

const pipelineSteps = [
  { index: 1, status: 'Pending_Staff', label: 'Staff Review' },
  { index: 2, status: 'Pending_Boss', label: 'Boss Review' },
  { index: 3, status: 'Approved', label: 'Approved' },
];

const receiptHistory = [
  { id: 1, event: 'CMart Weekend Carboot', date: '18 May 2026', booth: 'A-12', amount: 30.0, status: 'Paid' },
  { id: 2, event: 'CMart Weekend Carboot', date: '11 May 2026', booth: 'B-07', amount: 50.0, status: 'Paid' },
  { id: 3, event: 'CMart Special Market', date: '27 Apr 2026', booth: 'C-03', amount: 30.0, status: 'Refunded' },
];

const userDisplayName = computed(() => auth.user?.name || 'Vendor');
const contactDisplay = computed(() => auth.user?.phone_number || auth.user?.email || '—');

const latestBooking = computed(() => myBookings.value[0] || null);
const latestApprovedBooking = computed(() =>
  myBookings.value.find((b) => b.approval_status === 'Approved') || null,
);
const hasApprovedBooking = computed(() => Boolean(latestApprovedBooking.value));

const registeredProduct = computed(() =>
  latestBooking.value?.product_details || latestBooking.value?.product_category || '—',
);
const registeredCategory = computed(() =>
  latestBooking.value?.product_details ? latestBooking.value?.product_category : null,
);

const boothLabel = computed(() => {
  const booking = latestApprovedBooking.value;
  if (!booking) return '—';
  const prefix = String.fromCharCode(65 + (booking.id % 3));
  const num = String(booking.id).padStart(2, '0');
  return `${prefix}-${num}`;
});

onMounted(async () => {
  if (auth.token) {
    try {
      await auth.fetchMe();
    } catch {
      // Public fallback handled by router on protected routes
    }
  }
  await fetchMyBookings();
});

const fetchMyBookings = async () => {
  loadingBookings.value = true;
  try {
    const { data } = await api.get('/vendor/bookings');
    myBookings.value = Array.isArray(data) ? data : (data.data ?? []);
  } catch (e) {
    console.error('500 Internal Server Error: Unable to retrieve vendor bookings from the API.', e);
    toast.error('500 Internal Server Error: Unable to retrieve vendor bookings from the API.');
  } finally {
    loadingBookings.value = false;
  }
};

const viewPdf = async (bookingId) => {
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
  } catch (e) {
    console.error(`500 Internal Server Error: Unable to retrieve PDF for booking #${bookingId}.`, e);
    toast.error(`500 Internal Server Error: Unable to retrieve PDF for booking #${bookingId}.`);
  }
};

const resubmitBooking = async (booking) => {
  try {
    const payload = {};
    if (resubmitDates[booking.id]) payload.booking_date = resubmitDates[booking.id];

    const { data } = await api.patch(`/vendor/bookings/${booking.id}/resubmit`, payload);
    toast.success(data.message || '200 OK: Booking resubmitted successfully.');
    resubmitDates[booking.id] = '';
    await fetchMyBookings();
  } catch (e) {
    const message = e.response?.data?.message || '500 Internal Server Error: Unable to resubmit booking.';
    console.error(message, e);
    toast.error(message);
  }
};

const statusLabel = (status) => ({
  Pending_Staff: 'Pending Staff Review',
  Needs_Revision: 'Needs Revision',
  Pending_Boss: 'Pending Boss Review',
  Approved: 'Approved',
  Rejected: 'Rejected',
}[status] || status);

const statusBadgeClass = (status) => ({
  Pending_Staff: 'ml-badge bg-brand-100 text-brand-800',
  Pending_Boss: 'ml-badge bg-purple-100 text-purple-800',
  Needs_Revision: 'ml-badge bg-brand-100 text-brand-800',
  Approved: 'ml-badge bg-emerald-100 text-emerald-800',
  Rejected: 'ml-badge bg-rose-100 text-rose-800',
}[status] || 'ml-badge bg-ink-100 text-ink-700');

const progressIndex = (status) => {
  if (status === 'Pending_Staff' || status === 'Needs_Revision' || status === 'Rejected') return 1;
  if (status === 'Pending_Boss') return 2;
  if (status === 'Approved') return 3;
  return 0;
};

const progressWidth = (status) => {
  if (status === 'Approved') return '100%';
  if (status === 'Pending_Boss') return '50%';
  if (status === 'Pending_Staff' || status === 'Needs_Revision' || status === 'Rejected') return '0%';
  return '0%';
};

const progressBarClass = (status) => ({
  Pending_Staff: 'bg-brand-500',
  Pending_Boss: 'bg-purple-500',
  Needs_Revision: 'bg-brand-600',
  Approved: 'bg-emerald-500',
  Rejected: 'bg-rose-500',
}[status] || 'bg-ink-400');

const stepClass = (currentStatus, stepStatus) => {
  const active = progressIndex(currentStatus) >= progressIndex(stepStatus);
  if (!active) return 'border-ink-200 text-ink-400';
  return {
    Pending_Staff: 'border-brand-500 text-brand-700',
    Pending_Boss: 'border-purple-500 text-purple-700',
    Needs_Revision: 'border-brand-600 text-brand-700',
    Approved: 'border-emerald-500 text-emerald-700',
    Rejected: 'border-rose-500 text-rose-700',
  }[currentStatus] || 'border-ink-400 text-ink-700';
};

const receiptStatusClass = (status) => ({
  Paid: 'ml-badge bg-emerald-100 text-emerald-800',
  Refunded: 'ml-badge bg-amber-100 text-amber-800',
  Pending: 'ml-badge bg-brand-100 text-brand-800',
}[status] || 'ml-badge bg-ink-100 text-ink-700');
</script>
