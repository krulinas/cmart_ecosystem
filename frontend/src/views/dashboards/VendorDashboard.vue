<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50" data-testid="vendor-dashboard-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <div class="max-w-page mx-auto py-12 px-4 sm:px-6 lg:px-8 space-y-10">
      <VendorOnboardingBanner
        v-if="onboardingState !== 'active'"
        :state="onboardingState"
        class="mb-2"
        @review-booking="openLatestActionableBooking"
      />

      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <span class="ml-badge bg-brand-100 text-brand-700">Vendor Hub</span>
            <h1 class="mt-2 text-3xl sm:text-4xl font-black text-ink-900 tracking-tight">My Dashboard</h1>
            <p class="mt-2 text-base text-ink-500 leading-relaxed">
              Welcome back, {{ userDisplayName }}. Track approvals, booth details, and your vendor history in one place.
            </p>
          </div>
          <router-link
            to="/vendor-booking"
            data-testid="nav-booking-events"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 min-h-[44px] text-[15px] font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600 transition shrink-0"
          >
            {{ validBookings.length ? 'Book a Space' : 'Start Vendor Booking' }}
          </router-link>
        </div>
      </header>

      <nav
        aria-label="Dashboard sections"
        class="flex flex-wrap gap-2"
        data-testid="vendor-dashboard-section-nav"
      >
        <button
          v-for="link in dashboardSectionLinks"
          :key="link.targetId"
          type="button"
          class="rounded-full px-4 py-2 text-sm font-semibold transition border"
          :class="activeSectionId === link.targetId
            ? 'bg-brand-50 text-brand-700 border-brand-200 ring-1 ring-brand-200/80'
            : 'bg-white/80 text-ink-600 border-ink-200 hover:bg-brand-50/60 hover:text-brand-700 hover:border-brand-200'"
          :data-testid="link.testId"
          @click="scrollToDashboardSection(link.targetId)"
        >
          {{ link.label }}
        </button>
      </nav>

      <!-- My Bookings -->
      <section
        id="vendor-my-bookings"
        class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5"
        data-testid="my-bookings-root"
      >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
          <div>
            <h2 class="text-2xl font-extrabold text-ink-900">My Bookings</h2>
            <p class="text-base text-ink-500 leading-relaxed">Your booth requests at a glance. Open details for the full approval timeline.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <router-link
              to="/vendor-booking"
              class="ml-btn-primary"
            >
              {{ validBookings.length ? 'New Booking' : 'Start Vendor Booking' }}
            </router-link>
            <button class="ml-btn-ghost" :disabled="loadingBookings" @click="fetchMyBookings">
              {{ loadingBookings ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
        </div>

        <div class="mb-4">
          <input
            v-model="bookingSearchQuery"
            type="search"
            placeholder="Search bookings…"
            data-testid="booking-search"
            class="w-full sm:max-w-sm rounded-xl border border-ink-200 bg-white/80 px-4 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>

        <div class="flex flex-wrap gap-2 mb-5">
          <button
            v-for="tab in FILTER_TABS"
            :key="tab.id"
            type="button"
            class="rounded-full px-4 py-1.5 text-sm font-semibold transition"
            :class="filterTabClass(selectedBookingStatus === tab.id)"
            @click="selectedBookingStatus = tab.id"
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
            No bookings match your search.
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
                data-testid="booking-list-item"
                :data-booking-id="booking.id"
                :data-booking-status="booking.approval_status"
                class="hover:bg-brand-50/40 transition-colors"
              >
                <td class="px-4 py-3 font-semibold text-ink-900">#{{ booking.id }}</td>
                <td class="px-4 py-3 text-ink-600">{{ formatBookingDate(booking.booking_date) }}</td>
                <td class="px-4 py-3 text-ink-600">{{ boothTypeLabel(booking) }}</td>
                <td class="px-4 py-3 text-ink-600 max-w-[220px] truncate" :title="productSummary(booking)">
                  {{ productSummary(booking) }}
                </td>
                <td class="px-4 py-3">
                  <span :class="statusBadgeClass(booking.approval_status)" data-testid="booking-status">
                    {{ statusLabel(booking.approval_status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    class="ml-btn-ghost text-sm"
                    data-testid="booking-view-details"
                    @click="openBookingDetails(booking.id)"
                  >
                    View Details
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredBookings.length > VISIBLE_LIST_LIMIT" class="mt-4 flex justify-center">
          <button class="ml-btn-ghost text-sm font-semibold" @click="bookingsExpanded = !bookingsExpanded">
            {{ bookingsExpanded ? 'Show Less' : `View All Bookings (${filteredBookings.length})` }}
          </button>
        </div>
      </section>

      <VendorItemManager @changed="onVendorItemsChanged" />

      <!-- Event Passes & Business Profile -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
          <VendorEventPassesPanel
            ref="eventPassesRef"
            :vendor-name="userDisplayName"
            @download-pass="downloadPassPdf"
          />
        </div>

        <VendorBusinessProfileManager
          ref="profileManagerRef"
          @loaded="onBusinessProfileLoaded"
          @updated="onBusinessProfileUpdated"
        />
      </div>

      <VendorAnalyticsDashboard
        :analytics="vendorAnalytics"
        :loading="loadingInsights"
        :load-error="insightsError"
        @retry="fetchVendorInsights"
        @edit-profile="scrollToBusinessProfile"
        @manage-reuse="scrollToReuseListings"
      />

      <VendorHistoryReceipts
        :records="paymentRecords"
        :loading="loadingHistory"
        :load-error="historyError"
        @retry="fetchPaymentHistory"
        @view-document="openBookingDocument"
        @submit-payment="openPaymentSubmission"
      />
    </div>

    <VendorBookingDetailsModal
      v-model="showBookingModal"
      :booking-id="selectedBookingId"
      @refreshed="onBookingsRefreshed"
    />

    <VendorPaymentModal
      v-model="showPaymentModal"
      :booking-id="paymentBookingId"
      :amount="paymentInvoiceAmount"
      @submitted="onPaymentSubmitted"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import VendorBusinessProfileManager from '../../components/VendorBusinessProfileManager.vue';
import VendorEventPassesPanel from '../../components/VendorEventPassesPanel.vue';
import VendorItemManager from '../../components/VendorItemManager.vue';
import VendorAnalyticsDashboard from '../../components/VendorAnalyticsDashboard.vue';
import VendorHistoryReceipts from '../../components/VendorHistoryReceipts.vue';
import VendorOnboardingBanner from '../../components/vendor/VendorOnboardingBanner.vue';
import VendorBookingDetailsModal from '../../components/VendorBookingDetailsModal.vue';
import VendorPaymentModal from '../../components/VendorPaymentModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { VENDOR_DASHBOARD_SECTION_LINKS } from '../../config/navigation';
import {
  FILTER_TABS,
  boothTypeLabel,
  filterTabClass,
  formatBookingDate,
  isValidBookingDate,
  matchesStatusFilter,
  productSummary,
  statusBadgeClass,
  statusLabel,
} from '../../utils/bookingDisplay';
import { resolveVendorOnboardingState } from '../../utils/vendorOnboarding';

const toast = useToast();
const route = useRoute();
const auth = useAuthStore();

const dashboardSectionLinks = VENDOR_DASHBOARD_SECTION_LINKS;
const activeSectionId = ref('vendor-my-bookings');
let sectionObserver = null;

const scrollToDashboardSection = (targetId) => {
  activeSectionId.value = targetId;
  document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const syncSectionFromHash = () => {
  const hash = (route.hash || '').replace('#', '');
  if (!hash) return;
  const match = dashboardSectionLinks.find((link) => link.targetId === hash);
  if (match) {
    activeSectionId.value = match.targetId;
    requestAnimationFrame(() => {
      document.getElementById(match.targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
};

const setupSectionObserver = () => {
  if (sectionObserver) {
    sectionObserver.disconnect();
    sectionObserver = null;
  }

  if (typeof IntersectionObserver === 'undefined') return;

  sectionObserver = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
      if (visible[0]?.target?.id) {
        activeSectionId.value = visible[0].target.id;
      }
    },
    { rootMargin: '-20% 0px -55% 0px', threshold: [0.1, 0.35, 0.6] },
  );

  dashboardSectionLinks.forEach((link) => {
    const el = document.getElementById(link.targetId);
    if (el) sectionObserver.observe(el);
  });
};

const VISIBLE_LIST_LIMIT = 5;

const normalizeSearch = (value) => String(value ?? '').toLowerCase().trim();

const DEFAULT_ANALYTICS = {
  summary: {
    total_bookings: 0,
    upcoming_bookings: 0,
    completed_bookings: 0,
    cancelled_bookings: 0,
    rejected_bookings: 0,
    total_receipts: 0,
    total_paid_amount: 0,
    active_reuse_listings: 0,
    inactive_reuse_listings: 0,
    total_reuse_listings: 0,
    profile_completion_percent: 0,
    profile_missing_fields: [],
  },
  booth: {
    items_reused: 0,
    estimated_sales: 0,
    booth_status: 'No Active Booking',
    current_event: null,
    booth_number: null,
  },
  trends: { monthly_bookings: [], monthly_payments: [] },
  distributions: { booking_status: {}, reuse_listing_status: { active: 0, inactive: 0 } },
  recent_activity: [],
  latest: { booking: null, receipt: null, reuse_item: null },
};

const vendorAnalytics = ref(structuredClone(DEFAULT_ANALYTICS));
const loadingInsights = ref(false);
const insightsError = ref(false);
const profileManagerRef = ref(null);
const eventPassesRef = ref(null);

const myBookings = ref([]);
const loadingBookings = ref(false);
const bookingSearchQuery = ref('');
const selectedBookingStatus = ref('all');
const bookingsExpanded = ref(false);
const selectedBookingId = ref(null);
const showBookingModal = ref(false);
const showPaymentModal = ref(false);
const paymentBookingId = ref(null);
const paymentInvoiceAmount = ref(null);
const businessProfile = ref(null);

const paymentRecords = ref([]);
const loadingHistory = ref(false);
const historyError = ref(false);

const userDisplayName = computed(() => businessProfile.value?.business_name || auth.user?.name || 'Vendor');

const onboardingState = computed(() => resolveVendorOnboardingState(validBookings.value));

const openLatestActionableBooking = () => {
  const sorted = [...validBookings.value].sort((a, b) => (b.id ?? 0) - (a.id ?? 0));
  const latest = sorted[0];
  if (latest?.id) {
    openBookingDetails(latest.id);
  }
};

const onBusinessProfileLoaded = (profile) => {
  businessProfile.value = profile;
};

const onBusinessProfileUpdated = async (profile) => {
  businessProfile.value = profile;
  if (auth.user && profile?.business_name) {
    auth.user.name = profile.business_name;
    localStorage.setItem('carboot_cmart_user', JSON.stringify(auth.user));
  }
  await fetchVendorInsights();
};

const scrollToBusinessProfile = () => {
  scrollToDashboardSection('vendor-business-profile');
  profileManagerRef.value?.startEditing?.();
};

const scrollToReuseListings = () => scrollToDashboardSection('vendor-reuse-listings');

const onVendorItemsChanged = async () => {
  await fetchVendorInsights();
};

const onBookingsRefreshed = async () => {
  await fetchMyBookings();
  await eventPassesRef.value?.loadPasses?.();
};

const downloadPassPdf = async (bookingId) => {
  const id = bookingId;
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

const openPaymentSubmission = (row) => {
  paymentBookingId.value = row?.booking_id ?? null;
  paymentInvoiceAmount.value = row?.amount ?? null;
  showPaymentModal.value = true;
};

const onPaymentSubmitted = async () => {
  await fetchPaymentHistory();
};

const bookingMatchesSearch = (booking, query) => {
  if (!query) return true;

  const haystack = [
    booking.id,
    `#${booking.id}`,
    formatBookingDate(booking.booking_date),
    booking.booking_date,
    boothTypeLabel(booking),
    booking.product_category,
    booking.product_details,
    booking.approval_status,
    statusLabel(booking.approval_status),
    productSummary(booking),
  ]
    .filter((part) => part != null && part !== '')
    .join(' ')
    .toLowerCase();

  return haystack.includes(query);
};

const validBookings = computed(() =>
  myBookings.value.filter((booking) => isValidBookingDate(booking.booking_date)),
);

const filteredBookings = computed(() => {
  const query = normalizeSearch(bookingSearchQuery.value);

  return validBookings.value.filter(
    (booking) =>
      matchesStatusFilter(booking, selectedBookingStatus.value) &&
      bookingMatchesSearch(booking, query),
  );
});

const visibleBookings = computed(() =>
  bookingsExpanded.value
    ? filteredBookings.value
    : filteredBookings.value.slice(0, VISIBLE_LIST_LIMIT),
);

const filterCounts = computed(() =>
  FILTER_TABS.reduce((counts, tab) => {
    counts[tab.id] = validBookings.value.filter((booking) => matchesStatusFilter(booking, tab.id)).length;
    return counts;
  }, {}),
);

watch([bookingSearchQuery, selectedBookingStatus], () => {
  bookingsExpanded.value = false;
});

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
  syncSectionFromHash();
  setupSectionObserver();
});

onBeforeUnmount(() => {
  sectionObserver?.disconnect();
});

watch(() => route.hash, syncSectionFromHash);

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
    vendorAnalytics.value = {
      ...structuredClone(DEFAULT_ANALYTICS),
      ...data,
      summary: { ...DEFAULT_ANALYTICS.summary, ...(data.summary || {}) },
      booth: { ...DEFAULT_ANALYTICS.booth, ...(data.booth || {}) },
      trends: { ...DEFAULT_ANALYTICS.trends, ...(data.trends || {}) },
      distributions: { ...DEFAULT_ANALYTICS.distributions, ...(data.distributions || {}) },
      latest: { ...DEFAULT_ANALYTICS.latest, ...(data.latest || {}) },
      recent_activity: Array.isArray(data.recent_activity) ? data.recent_activity : [],
    };
  } catch (e) {
    console.error('Unable to retrieve vendor analytics from the API.', e);
    insightsError.value = true;
    vendorAnalytics.value = structuredClone(DEFAULT_ANALYTICS);
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
