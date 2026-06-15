<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50">
    <AppNavbar variant="vendor" />

    <div class="max-w-6xl mx-auto py-10 px-4 sm:px-6 space-y-8">
      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <span class="ml-badge bg-brand-100 text-brand-700">Vendor Hub</span>
            <h1 class="mt-2 text-3xl font-black text-ink-900 tracking-tight">My Dashboard</h1>
            <p class="mt-1 text-sm text-ink-500">
              Welcome back, {{ userDisplayName }}. Track approvals, booth details, and your vendor history in one place.
            </p>
          </div>
          <router-link
            v-if="auth.isApprovedVendor"
            to="/vendor-booking"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600 transition shrink-0"
          >
            Book a Space
          </router-link>
        </div>
      </header>

      <!-- My Bookings -->
      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
          <div>
            <h2 class="text-xl font-extrabold text-ink-900">My Bookings</h2>
            <p class="text-sm text-ink-500">Your booth requests at a glance. Open details for the full approval timeline.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <router-link
              v-if="auth.isApprovedVendor"
              to="/vendor-booking"
              class="ml-btn-primary text-sm"
            >
              New Booking
            </router-link>
            <button class="ml-btn-ghost" :disabled="loadingBookings" @click="fetchMyBookings">
              {{ loadingBookings ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-5">
          <button
            v-for="tab in FILTER_TABS"
            :key="tab.id"
            type="button"
            class="rounded-full px-4 py-1.5 text-sm font-semibold transition"
            :class="filterTabClass(statusFilter === tab.id)"
            @click="statusFilter = tab.id"
          >
            {{ tab.label }}
            <span class="ml-1 opacity-75">({{ filterCounts[tab.id] || 0 }})</span>
          </button>
        </div>

        <div v-if="!filteredBookings.length" class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500">
          <template v-if="!validBookings.length">
            No booking records are currently available.
            <router-link to="/vendor-booking" class="mt-3 block text-brand-600 font-semibold hover:text-brand-700">
              Submit your first booking →
            </router-link>
          </template>
          <template v-else>
            No bookings match this filter.
          </template>
        </div>

        <div v-else class="overflow-x-auto rounded-2xl border border-ink-100">
          <table class="min-w-full divide-y divide-ink-100 text-sm">
            <thead class="bg-ink-50/80">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Booking ID</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Event Date</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Booth Type</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Product</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Status</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink-100 bg-white/70">
              <tr
                v-for="booking in visibleBookings"
                :key="booking.id"
                class="hover:bg-brand-50/40 transition-colors"
              >
                <td class="px-4 py-3 font-semibold text-ink-900">#{{ booking.id }}</td>
                <td class="px-4 py-3 text-ink-600">{{ formatBookingDate(booking.booking_date) }}</td>
                <td class="px-4 py-3 text-ink-600">{{ boothTypeLabel(booking) }}</td>
                <td class="px-4 py-3 text-ink-600 max-w-[220px] truncate" :title="productSummary(booking)">
                  {{ productSummary(booking) }}
                </td>
                <td class="px-4 py-3">
                  <span :class="statusBadgeClass(booking.approval_status)">
                    {{ statusLabel(booking.approval_status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button class="ml-btn-ghost text-sm" @click="openBookingDetails(booking.id)">
                    View Details
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredBookings.length > 5" class="mt-4 flex justify-center">
          <button class="ml-btn-ghost text-sm font-semibold" @click="showAllBookings = !showAllBookings">
            {{ showAllBookings ? 'Show Latest 5' : `View All Bookings (${filteredBookings.length})` }}
          </button>
        </div>
      </section>

      <!-- Vendor Pass & Business Profile -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
              <span
                v-if="passState === 'active'"
                class="ml-badge bg-emerald-100 text-emerald-800"
              >
                Approved
              </span>
              <span
                v-else-if="passState === 'invalid'"
                class="ml-badge bg-rose-100 text-rose-800"
              >
                Pass Unavailable
              </span>
              <span v-else class="ml-badge bg-ink-100 text-ink-700">Pending Approval</span>
              <h2 class="mt-2 text-xl font-extrabold text-ink-900">Vendor Pass &amp; Business Profile</h2>
              <p class="mt-1 text-sm text-ink-500 max-w-xl">
                Your approved vendor pass for event check-in, booth confirmation, and staff verification.
              </p>
            </div>
          </div>

          <div v-if="passState === 'active' && passBooking" class="grid grid-cols-1 xl:grid-cols-5 gap-6">
            <div class="xl:col-span-3 rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white p-5 sm:p-6 shadow-inner">
              <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="ml-badge bg-emerald-100 text-emerald-800">Approved</span>
                <span class="ml-badge bg-brand-100 text-brand-800">Booking #{{ passBooking.id }}</span>
              </div>

              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Vendor</dt>
                  <dd class="mt-1 text-base font-semibold text-ink-900">{{ userDisplayName }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Assigned Booth</dt>
                  <dd class="mt-1 text-base font-semibold text-ink-900">{{ boothLabel }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Booth Type</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ boothTypeLabel(passBooking) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Event Date</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ passEventDateLong }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Event Time</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ passEventTimeLabel }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Product</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ passProductLabel }}</dd>
                </div>
              </dl>

              <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" class="ml-btn-primary" @click="openPassModal">View Full Pass</button>
                <button type="button" class="ml-btn-ghost" @click="downloadPassPdf">Download Pass</button>
              </div>
            </div>

            <div class="xl:col-span-2 rounded-2xl border border-ink-200 bg-white p-5 text-center shadow-sm">
              <p class="text-xs font-bold uppercase tracking-wider text-ink-500">Verification QR</p>
              <img
                :src="passQrImageUrl"
                :alt="`Verification QR for booking ${passBooking.id}`"
                class="mx-auto mt-4 h-44 w-44 rounded-xl border border-ink-100 bg-white p-2 object-contain"
              />
              <p class="mt-3 text-xs font-semibold text-ink-600 break-all">{{ passVerifyUrl }}</p>
              <p class="mt-2 text-xs text-ink-400">Scan at event entrance for staff verification.</p>
            </div>
          </div>

          <div
            v-else-if="passState === 'invalid'"
            class="rounded-2xl border border-rose-200 bg-rose-50/70 p-8 text-center"
          >
            <h3 class="text-lg font-bold text-rose-900">Vendor pass unavailable</h3>
            <p class="mt-2 text-sm text-rose-800">
              This booking is no longer active. Contact CMart staff if you need assistance.
            </p>
          </div>

          <div
            v-else
            class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/60 p-8 sm:p-10 text-center"
          >
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-ink-200/80 text-ink-500">
              <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h3 class="mt-4 text-lg font-bold text-ink-900">Your pass will be available once the booking is approved</h3>
            <p class="mt-2 text-sm text-ink-500 max-w-md mx-auto">
              Complete the approval pipeline above to unlock your vendor event pass, QR verification, and booth confirmation.
            </p>
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

          <button type="button" class="mt-6 ml-btn-ghost w-full" @click="showProfileModal = true">
            Edit Profile
          </button>
        </aside>
      </div>

      <VendorEventInsights
        :items-reused="vendorInsights.items_reused"
        :estimated-sales="vendorInsights.estimated_sales"
        :booth-status="vendorInsights.booth_status"
        :current-event="vendorInsights.current_event"
        :booth-number="vendorInsights.booth_number"
        :loading="loadingInsights"
        :load-error="insightsError"
        @retry="fetchVendorInsights"
      />

      <VendorHistoryReceipts
        :records="paymentRecords"
        :loading="loadingHistory"
        :load-error="historyError"
        @retry="fetchPaymentHistory"
        @view-document="openBookingDocument"
      />
    </div>

    <VendorBookingDetailsModal
      v-model="showBookingModal"
      :booking-id="selectedBookingId"
      @refreshed="fetchMyBookings"
    />

    <VendorPassModal
      v-model="showPassModal"
      :pass="passBooking"
      :vendor-name="userDisplayName"
      :booth-label="boothLabel"
      :event-date-label="passEventDateLong"
      :event-time-label="passEventTimeLabel"
      :product-label="passProductLabel"
      :verify-url="passVerifyUrl"
      :qr-image-url="passQrImageUrl"
      @download="downloadPassPdf"
    />

    <VendorBusinessProfileModal v-model="showProfileModal" :profile="businessProfilePayload" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import VendorEventInsights from '../../components/VendorEventInsights.vue';
import VendorHistoryReceipts from '../../components/VendorHistoryReceipts.vue';
import VendorBookingDetailsModal from '../../components/VendorBookingDetailsModal.vue';
import VendorPassModal from '../../components/VendorPassModal.vue';
import VendorBusinessProfileModal from '../../components/VendorBusinessProfileModal.vue';
import api from '../../services/api';
import {
  buildQrImageUrl,
  buildVerifyUrl,
  formatBookingDateLong,
  formatEventTimeLabel,
  passStateForBooking,
  productDetailsLabel,
} from '../../utils/vendorPass';
import { useAuthStore } from '../../stores/auth';
import {
  FILTER_TABS,
  boothLabelForBooking,
  boothTypeLabel,
  filterTabClass,
  formatBookingDate,
  isValidBookingDate,
  matchesStatusFilter,
  productSummary,
  statusBadgeClass,
  statusLabel,
} from '../../utils/bookingDisplay';

const toast = useToast();
const auth = useAuthStore();

const DEFAULT_INSIGHTS = {
  items_reused: 0,
  estimated_sales: 0,
  booth_status: 'No Active Booking',
  current_event: null,
  booth_number: null,
};

const vendorInsights = ref({ ...DEFAULT_INSIGHTS });
const loadingInsights = ref(false);
const insightsError = ref(false);

const myBookings = ref([]);
const loadingBookings = ref(false);
const statusFilter = ref('all');
const showAllBookings = ref(false);
const selectedBookingId = ref(null);
const showBookingModal = ref(false);
const showPassModal = ref(false);
const showProfileModal = ref(false);

const paymentRecords = ref([]);
const loadingHistory = ref(false);
const historyError = ref(false);

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

const passBooking = computed(() => latestApprovedBooking.value);

const passState = computed(() => passStateForBooking(passBooking.value));

const boothLabel = computed(() => boothLabelForBooking(passBooking.value));

const passEventDateLong = computed(() =>
  formatBookingDateLong(passBooking.value?.booking_date),
);

const passEventTimeLabel = computed(() => formatEventTimeLabel(passBooking.value?.booking_date));

const passProductLabel = computed(() => productDetailsLabel(passBooking.value));

const passVerifyUrl = computed(() =>
  passBooking.value?.id ? buildVerifyUrl(passBooking.value.id) : '',
);

const passQrImageUrl = computed(() =>
  passBooking.value?.id ? buildQrImageUrl(passBooking.value.id) : '',
);

const businessProfilePayload = computed(() => ({
  name: auth.user?.name || '',
  phone_number: auth.user?.phone_number || '',
  email: auth.user?.email || '',
  product_summary: registeredProduct.value === '—' ? '' : registeredProduct.value,
}));

const openPassModal = () => {
  if (!passBooking.value) return;
  showPassModal.value = true;
};

const downloadPassPdf = async (bookingId) => {
  const id = bookingId || passBooking.value?.id;
  if (!id) {
    toast.error('No approved booking pass is available to download yet.');
    return;
  }

  try {
    const response = await api.get(`/bookings/${id}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
    toast.success('Booking document opened.');
  } catch (error) {
    console.error('Unable to download booking document PDF:', error);
    toast.error('Unable to open booking document.');
  }
};

const openBookingDocument = (bookingId) => {
  downloadPassPdf(bookingId);
};

const validBookings = computed(() =>
  myBookings.value.filter((booking) => isValidBookingDate(booking.booking_date)),
);

const filteredBookings = computed(() =>
  validBookings.value.filter((booking) => matchesStatusFilter(booking, statusFilter.value)),
);

const visibleBookings = computed(() =>
  showAllBookings.value ? filteredBookings.value : filteredBookings.value.slice(0, 5),
);

const filterCounts = computed(() =>
  FILTER_TABS.reduce((counts, tab) => {
    counts[tab.id] = validBookings.value.filter((booking) => matchesStatusFilter(booking, tab.id)).length;
    return counts;
  }, {}),
);

const openBookingDetails = (bookingId) => {
  selectedBookingId.value = bookingId;
  showBookingModal.value = true;
};

onMounted(async () => {
  if (auth.token) {
    try {
      await auth.fetchMe();
    } catch {
      // Public fallback handled by router on protected routes
    }
  }
  await Promise.all([fetchMyBookings(), fetchVendorInsights(), fetchPaymentHistory()]);
});

const fetchPaymentHistory = async () => {
  loadingHistory.value = true;
  historyError.value = false;
  try {
    const { data } = await api.get('/vendor/history-receipts');
    paymentRecords.value = Array.isArray(data?.records) ? data.records : [];
  } catch (e) {
    console.error('Unable to retrieve vendor payment history from the API.', e);
    historyError.value = true;
    paymentRecords.value = [];
  } finally {
    loadingHistory.value = false;
  }
};

const fetchVendorInsights = async () => {
  loadingInsights.value = true;
  insightsError.value = false;
  try {
    const { data } = await api.get('/vendor/analytics/me');
    vendorInsights.value = {
      ...DEFAULT_INSIGHTS,
      ...data,
    };
  } catch (e) {
    console.error('Unable to retrieve vendor analytics from the API.', e);
    insightsError.value = true;
    vendorInsights.value = { ...DEFAULT_INSIGHTS };
  } finally {
    loadingInsights.value = false;
  }
};

const fetchMyBookings = async () => {
  loadingBookings.value = true;
  try {
    const { data } = await api.get('/vendor/bookings');
    myBookings.value = Array.isArray(data) ? data : (data.data ?? []);
  } catch (e) {
    console.error('Unable to retrieve vendor bookings from the API.', e);
    toast.error('Unable to retrieve vendor bookings.');
  } finally {
    loadingBookings.value = false;
  }
};
</script>
