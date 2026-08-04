<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/40 to-white" data-testid="vendor-dashboard-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <div class="max-w-page mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <VendorOnboardingBanner
        v-if="onboardingState !== 'active'"
        :state="onboardingState"
        @review-booking="openLatestActionableBooking"
      />

      <!-- Compact header -->
      <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-black text-ink-900 tracking-tight">
            Hi, {{ userDisplayName }}
          </h1>
          <p class="mt-1 text-sm text-ink-500">Your booth overview for today's CMart work.</p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
          <router-link
            to="/vendor-booking"
            data-testid="nav-booking-events"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 min-h-[44px] text-[15px] font-bold text-white shadow-md shadow-brand-500/20 hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 transition"
          >
            {{ validBookings.length ? 'Book a Space' : 'Start Vendor Booking' }}
          </router-link>
          <button
            type="button"
            class="ml-btn-ghost min-h-[44px]"
            :disabled="loadingBookings"
            @click="refreshPrimaryData"
          >
            {{ loadingBookings ? 'Refreshing…' : 'Refresh' }}
          </button>
        </div>
      </header>

      <!-- Above the fold: next booking + status -->
      <VendorDashboardFocus
        :booking="focusBooking"
        :payment-record="focusPaymentRecord"
        :booth-status="vendorAnalytics.booth?.booth_status"
        :booth-number="vendorAnalytics.booth?.booth_number"
        :current-event-label="vendorAnalytics.booth?.current_event"
        :loading="loadingBookings"
        @primary-action="handleFocusPrimaryAction"
      />

      <!-- Announcements (real published news only) -->
      <section
        v-if="announcements.length"
        class="rounded-2xl border border-sky-100 bg-sky-50/70 px-4 py-4 sm:px-5"
        data-testid="vendor-announcements"
        aria-label="Event announcements"
      >
        <div class="flex items-center justify-between gap-3 mb-3">
          <h2 class="text-sm font-bold uppercase tracking-wider text-sky-800">Announcements</h2>
          <router-link to="/community" class="text-sm font-semibold text-brand-700 hover:text-brand-800">
            View more
          </router-link>
        </div>
        <ul class="space-y-2">
          <li
            v-for="item in announcements"
            :key="item.id"
            class="rounded-xl border border-white/80 bg-white/90 px-4 py-3"
          >
            <p class="text-sm font-bold text-ink-900 leading-snug">{{ item.title }}</p>
            <p class="mt-0.5 text-xs text-ink-500">
              <span v-if="item.category">{{ item.category }} · </span>{{ item.dateLabel }}
            </p>
          </li>
        </ul>
      </section>

      <!-- Compact upcoming bookings -->
      <section
        id="vendor-my-bookings"
        class="rounded-2xl border border-ink-100 bg-white p-5 sm:p-6 shadow-sm"
        data-testid="my-bookings-root"
      >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
          <div>
            <h2 class="text-lg font-extrabold text-ink-900">My Bookings</h2>
            <p class="text-sm text-ink-500">Upcoming first. Open details when you need the full timeline.</p>
          </div>
          <router-link
            to="/vendor-booking"
            class="text-sm font-semibold text-brand-700 hover:text-brand-800 min-h-[44px] inline-flex items-center"
          >
            {{ validBookings.length ? 'New booking' : 'Start booking' }}
          </router-link>
        </div>

        <div v-if="loadingBookings" class="space-y-2" aria-busy="true">
          <div v-for="n in 2" :key="n" class="h-16 rounded-xl bg-ink-100 animate-pulse"></div>
        </div>

        <div
          v-else-if="!priorityBookings.length"
          class="rounded-xl border border-dashed border-ink-200 bg-ink-50/60 px-4 py-8 text-center text-sm text-ink-500"
        >
          No booking records are currently available.
          <router-link to="/vendor-booking" class="mt-2 block font-semibold text-brand-700 hover:text-brand-800">
            Submit your first booking →
          </router-link>
        </div>

        <ul v-else class="space-y-2">
          <li
            v-for="booking in compactBookings"
            :key="booking.id"
            data-testid="booking-list-item"
            :data-booking-id="booking.id"
            :data-booking-status="booking.approval_status"
            class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border border-ink-100 bg-ink-50/40 px-4 py-3 hover:border-brand-200 hover:bg-brand-50/30 transition"
          >
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold text-ink-900 truncate">
                {{ booking.event_label || booking.carboot_event?.title || formatBookingDate(booking.booking_date) }}
                <span class="font-semibold text-ink-500"> · #{{ booking.id }}</span>
              </p>
              <p class="mt-0.5 text-xs text-ink-500">
                {{ formatBookingDate(booking.booking_date) }}
                <span v-if="boothTypeLabel(booking)"> · {{ boothTypeLabel(booking) }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span :class="statusBadgeClass(booking.approval_status)" data-testid="booking-status">
                {{ statusLabel(booking.approval_status) }}
              </span>
              <button
                type="button"
                class="ml-btn-ghost text-sm min-h-[44px]"
                data-testid="booking-view-details"
                @click="openBookingDetails(booking.id)"
              >
                View Details
              </button>
            </div>
          </li>
        </ul>

        <div v-if="priorityBookings.length > COMPACT_BOOKING_LIMIT || bookingsExpanded" class="mt-4">
          <button
            type="button"
            class="text-sm font-semibold text-brand-700 hover:text-brand-800 min-h-[44px] inline-flex items-center"
            data-testid="view-all-bookings"
            @click="toggleBookingsExpanded"
          >
            {{ bookingsExpanded ? 'Show less' : `View all bookings (${priorityBookings.length})` }}
          </button>
        </div>

        <!-- Full list tools (only when expanded) -->
        <div v-if="bookingsExpanded" class="mt-5 space-y-4 border-t border-ink-100 pt-5">
          <div class="flex flex-col sm:flex-row gap-3">
            <input
              v-model="bookingSearchQuery"
              type="search"
              placeholder="Search bookings…"
              data-testid="booking-search"
              class="w-full sm:max-w-sm rounded-xl border border-ink-200 bg-white px-4 py-2.5 min-h-[44px] text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
            />
            <button class="ml-btn-ghost min-h-[44px]" :disabled="loadingBookings" @click="fetchMyBookings">
              {{ loadingBookings ? 'Refreshing…' : 'Refresh list' }}
            </button>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="tab in FILTER_TABS"
              :key="tab.id"
              type="button"
              class="rounded-full px-4 py-2 min-h-[40px] text-sm font-semibold transition"
              :class="filterTabClass(selectedBookingStatus === tab.id)"
              @click="selectedBookingStatus = tab.id"
            >
              {{ tab.label }}
              <span class="ml-1 opacity-75">({{ filterCounts[tab.id] || 0 }})</span>
            </button>
          </div>

          <div v-if="!filteredBookings.length" class="rounded-xl border border-dashed border-ink-200 px-4 py-6 text-center text-sm text-ink-500">
            No bookings match your search.
          </div>

          <div v-else class="overflow-x-auto rounded-xl border border-ink-100">
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
              <tbody class="divide-y divide-ink-100 bg-white">
                <tr
                  v-for="booking in filteredBookings"
                  :key="`full-${booking.id}`"
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
                    <button class="ml-btn-ghost text-sm min-h-[44px]" @click="openBookingDetails(booking.id)">
                      View Details
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Secondary tools -->
      <nav
        aria-label="More vendor tools"
        class="flex flex-wrap gap-2"
        data-testid="vendor-dashboard-section-nav"
      >
        <button
          v-for="link in dashboardSectionLinks"
          :key="link.targetId"
          type="button"
          class="rounded-full px-4 py-2 min-h-[40px] text-sm font-semibold transition border"
          :class="activeSectionId === link.targetId
            ? 'bg-brand-50 text-brand-700 border-brand-200'
            : 'bg-white text-ink-600 border-ink-200 hover:bg-brand-50/60 hover:text-brand-700 hover:border-brand-200'"
          :data-testid="link.testId"
          @click="scrollToDashboardSection(link.targetId)"
        >
          {{ link.label }}
        </button>
        <button
          type="button"
          class="rounded-full px-4 py-2 min-h-[40px] text-sm font-semibold transition border border-ink-200 bg-white text-ink-600 hover:bg-ink-50"
          data-testid="dash-nav-insights"
          :aria-expanded="showAnalytics"
          @click="toggleAnalytics"
        >
          {{ showAnalytics ? 'Hide insights' : 'View insights' }}
        </button>
        <button
          v-if="hasPaymentHistoryEntry"
          type="button"
          class="rounded-full px-4 py-2 min-h-[40px] text-sm font-semibold transition border border-ink-200 bg-white text-ink-600 hover:bg-ink-50"
          data-testid="dash-nav-receipts"
          :aria-expanded="showReceipts"
          @click="toggleReceipts"
        >
          {{ showReceipts ? 'Hide payment history' : 'Payment history' }}
        </button>
      </nav>

      <VendorItemManager ref="itemManagerRef" @changed="onVendorItemsChanged" />

      <VendorItemReservationsPanel @changed="onVendorReservationsChanged" />

      <MyItemReservationsPanel />

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
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

      <!-- Progressive disclosure: analytics -->
      <div id="vendor-analytics" class="scroll-mt-24">
        <div
          v-if="!showAnalytics"
          class="rounded-2xl border border-dashed border-ink-200 bg-white/70 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
        >
          <div>
            <p class="text-sm font-bold text-ink-800">Analytics &amp; Reports</p>
            <p class="text-sm text-ink-500">Optional trends, exports, and profile readiness.</p>
          </div>
          <button
            type="button"
            class="ml-btn-ghost min-h-[44px] shrink-0"
            data-testid="open-vendor-insights"
            @click="toggleAnalytics"
          >
            View insights
          </button>
        </div>

        <VendorAnalyticsDashboard
          v-else
          :analytics="vendorAnalytics"
          :loading="loadingInsights"
          :load-error="insightsError"
          @retry="fetchVendorInsights"
          @edit-profile="scrollToBusinessProfile"
          @manage-reuse="scrollToReuseListings"
          @close="showAnalytics = false"
        />
      </div>

      <!-- Progressive disclosure: payment history (hidden when empty) -->
      <div
        v-if="hasPaymentHistoryEntry"
        id="vendor-history-receipts"
        class="scroll-mt-24"
      >
        <div
          v-if="!showReceipts"
          class="rounded-2xl border border-dashed border-ink-200 bg-white/70 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
        >
          <div>
            <p class="text-sm font-bold text-ink-800">Payment history</p>
            <p class="text-sm text-ink-500">Past invoices and receipts for your booth bookings.</p>
          </div>
          <button
            type="button"
            class="ml-btn-ghost min-h-[44px] shrink-0"
            data-testid="open-vendor-receipts"
            @click="toggleReceipts"
          >
            View receipts
          </button>
        </div>

        <VendorHistoryReceipts
          v-else
          :records="paymentRecords"
          :loading="loadingHistory"
          :load-error="historyError"
          @retry="fetchPaymentHistory"
          @view-document="openBookingDocument"
          @submit-payment="openPaymentSubmission"
          @close="showReceipts = false"
        />
      </div>
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
import VendorItemReservationsPanel from '../../components/VendorItemReservationsPanel.vue';
import MyItemReservationsPanel from '../../components/MyItemReservationsPanel.vue';
import VendorAnalyticsDashboard from '../../components/VendorAnalyticsDashboard.vue';
import VendorHistoryReceipts from '../../components/VendorHistoryReceipts.vue';
import VendorOnboardingBanner from '../../components/vendor/VendorOnboardingBanner.vue';
import VendorDashboardFocus from '../../components/vendor/VendorDashboardFocus.vue';
import VendorBookingDetailsModal from '../../components/VendorBookingDetailsModal.vue';
import VendorPaymentModal from '../../components/VendorPaymentModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { VENDOR_DASHBOARD_SECTION_LINKS } from '../../config/navigation';
import {
  FILTER_TABS,
  boothTypeLabel,
  canVendorProceedToDemoPayment,
  filterTabClass,
  formatBookingDate,
  isTerminalBookingStatus,
  isValidBookingDate,
  matchesStatusFilter,
  productSummary,
  statusBadgeClass,
  statusLabel,
} from '../../utils/bookingDisplay';
import { mapApiNewsToCard } from '../../utils/newsDisplay';
import { resolveVendorOnboardingState } from '../../utils/vendorOnboarding';

const toast = useToast();
const route = useRoute();
const auth = useAuthStore();

const dashboardSectionLinks = VENDOR_DASHBOARD_SECTION_LINKS;
const activeSectionId = ref('vendor-my-bookings');
let sectionObserver = null;

const COMPACT_BOOKING_LIMIT = 2;
const MY_TZ = 'Asia/Kuala_Lumpur';

const showAnalytics = ref(false);
const showReceipts = ref(false);

const scrollToDashboardSection = (targetId) => {
  activeSectionId.value = targetId;
  document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const toggleAnalytics = () => {
  showAnalytics.value = !showAnalytics.value;
  if (showAnalytics.value) {
    requestAnimationFrame(() => {
      document.getElementById('vendor-analytics')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
};

const toggleReceipts = () => {
  showReceipts.value = !showReceipts.value;
  if (showReceipts.value) {
    requestAnimationFrame(() => {
      document.getElementById('vendor-history-receipts')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
};

const syncSectionFromHash = () => {
  const hash = (route.hash || '').replace('#', '');
  if (!hash) return;
  if (hash === 'vendor-analytics') {
    showAnalytics.value = true;
    activeSectionId.value = hash;
    requestAnimationFrame(() => {
      document.getElementById('vendor-analytics')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    return;
  }
  if (hash === 'vendor-history-receipts') {
    showReceipts.value = true;
    activeSectionId.value = hash;
    requestAnimationFrame(() => {
      document.getElementById('vendor-history-receipts')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    return;
  }
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

  [...dashboardSectionLinks.map((link) => link.targetId), 'vendor-analytics', 'vendor-history-receipts', 'vendor-my-bookings'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) sectionObserver.observe(el);
  });
};

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
const itemManagerRef = ref(null);

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
const announcements = ref([]);

const paymentRecords = ref([]);
const loadingHistory = ref(false);
const historyError = ref(false);

const userDisplayName = computed(() => businessProfile.value?.business_name || auth.user?.name || 'Vendor');

const onboardingState = computed(() => resolveVendorOnboardingState(validBookings.value));

const todayKey = () => new Date().toLocaleDateString('en-CA', { timeZone: MY_TZ });

const bookingDateKey = (booking) => {
  const raw = String(booking?.booking_date || '').slice(0, 10);
  return isValidBookingDate(raw) ? raw : null;
};

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

const onVendorReservationsChanged = async () => {
  await itemManagerRef.value?.loadItems?.();
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
  paymentBookingId.value = row?.booking_id ?? row?.id ?? null;
  paymentInvoiceAmount.value = row?.amount ?? row?.invoice?.amount ?? null;
  showPaymentModal.value = true;
};

const handleFocusPrimaryAction = (action) => {
  if (!action) return;
  if (action.type === 'pay') {
    openPaymentSubmission({
      booking_id: action.bookingId,
      amount: action.amount,
    });
    return;
  }
  if (action.type === 'view-document' && action.bookingId) {
    openBookingDocument(action.bookingId);
    return;
  }
  if (action.type === 'view-booking' && action.bookingId) {
    openBookingDetails(action.bookingId);
  }
};

const onPaymentSubmitted = async () => {
  await Promise.all([fetchPaymentHistory(), fetchMyBookings()]);
};

const refreshPrimaryData = async () => {
  await Promise.all([fetchMyBookings(), fetchPaymentHistory(), fetchAnnouncements()]);
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

const sortBookingsByPriority = (bookings) => {
  const today = todayKey();
  return [...bookings].sort((a, b) => {
    const aKey = bookingDateKey(a) || '9999-99-99';
    const bKey = bookingDateKey(b) || '9999-99-99';
    const aUpcoming = aKey >= today;
    const bUpcoming = bKey >= today;
    if (aUpcoming !== bUpcoming) return aUpcoming ? -1 : 1;
    if (aKey !== bKey) return aUpcoming ? aKey.localeCompare(bKey) : bKey.localeCompare(aKey);
    return (b.id ?? 0) - (a.id ?? 0);
  });
};

const priorityBookings = computed(() => sortBookingsByPriority(validBookings.value));

const focusBooking = computed(() => {
  const today = todayKey();
  const candidates = priorityBookings.value.filter((booking) => {
    if (isTerminalBookingStatus(booking.approval_status)) return false;
    const key = bookingDateKey(booking);
    return Boolean(key && key >= today);
  });

  const actionable = candidates.find(
    (booking) =>
      booking.approval_status === 'Needs_Revision'
      || canVendorProceedToDemoPayment(booking)
      || ['Pending_Organizer', 'Pending_Staff', 'Pending_Boss'].includes(booking.approval_status),
  );

  return actionable || candidates[0] || priorityBookings.value[0] || null;
});

const focusPaymentRecord = computed(() => {
  const bookingId = focusBooking.value?.id;
  if (!bookingId) return null;
  return paymentRecords.value.find((row) => Number(row.booking_id) === Number(bookingId)) || null;
});

/** Show payment-history entry only when records exist or a load error needs recovery. */
const hasPaymentHistoryEntry = computed(() =>
  historyError.value || paymentRecords.value.length > 0,
);

const compactBookings = computed(() =>
  bookingsExpanded.value
    ? []
    : priorityBookings.value.slice(0, COMPACT_BOOKING_LIMIT),
);

const filteredBookings = computed(() => {
  const query = String(bookingSearchQuery.value ?? '').toLowerCase().trim();

  return priorityBookings.value.filter(
    (booking) =>
      matchesStatusFilter(booking, selectedBookingStatus.value) &&
      bookingMatchesSearch(booking, query),
  );
});

const filterCounts = computed(() =>
  FILTER_TABS.reduce((counts, tab) => {
    counts[tab.id] = validBookings.value.filter((booking) => matchesStatusFilter(booking, tab.id)).length;
    return counts;
  }, {}),
);

const toggleBookingsExpanded = () => {
  bookingsExpanded.value = !bookingsExpanded.value;
};

watch([bookingSearchQuery, selectedBookingStatus], () => {
  if (!bookingsExpanded.value) return;
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
  await Promise.all([
    fetchMyBookings(),
    fetchVendorInsights(),
    fetchPaymentHistory(),
    fetchAnnouncements(),
  ]);
  syncSectionFromHash();
  setupSectionObserver();
});

onBeforeUnmount(() => {
  sectionObserver?.disconnect();
});

watch(() => route.hash, syncSectionFromHash);

const fetchAnnouncements = async () => {
  try {
    const { data } = await api.get('/news');
    const rows = Array.isArray(data) ? data : (data?.data ?? []);
    announcements.value = rows
      .slice(0, 2)
      .map((row) => {
        const card = mapApiNewsToCard(row);
        return {
          id: card.id,
          title: card.title,
          category: card.category || null,
          dateLabel: card.publishedDateShort || '',
        };
      })
      .filter((row) => row.title);
  } catch (error) {
    console.error('Unable to retrieve announcements for vendor dashboard:', error);
    announcements.value = [];
  }
};

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
      summary: { ...DEFAULT_ANALYTICS.summary, ...data.summary },
      booth: { ...DEFAULT_ANALYTICS.booth, ...data.booth },
      trends: { ...DEFAULT_ANALYTICS.trends, ...data.trends },
      distributions: { ...DEFAULT_ANALYTICS.distributions, ...data.distributions },
      latest: { ...DEFAULT_ANALYTICS.latest, ...data.latest },
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
