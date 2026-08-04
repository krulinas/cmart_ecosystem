<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/40 to-white" data-testid="vendor-dashboard-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <div class="max-w-page mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <VendorOnboardingBanner
        v-if="onboardingState !== 'active'"
        :state="onboardingState"
        @review-booking="openLatestActionableBooking"
      />

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

      <VendorDashboardFocus
        :booking="focusBooking"
        :payment-record="null"
        :booth-status="focusBooking ? statusLabel(focusBooking.approval_status) : null"
        :booth-number="focusBooking ? siteLabelsForBooking(focusBooking) : null"
        :current-event-label="focusBooking?.event_label || focusBooking?.carboot_event?.title || null"
        :loading="loadingBookings"
        @primary-action="handleFocusPrimaryAction"
      />

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

      <section
        class="rounded-2xl border border-ink-100 bg-white p-5 sm:p-6 shadow-sm"
        data-testid="my-bookings-root"
      >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
          <div>
            <h2 class="text-lg font-extrabold text-ink-900">Upcoming bookings</h2>
            <p class="text-sm text-ink-500">Next two upcoming records. Full list lives under Manage.</p>
          </div>
          <router-link
            to="/vendor/manage/bookings"
            class="text-sm font-semibold text-brand-700 hover:text-brand-800 min-h-[44px] inline-flex items-center"
            data-testid="dashboard-view-all-bookings"
          >
            View all bookings
          </router-link>
        </div>

        <div v-if="loadingBookings" class="space-y-2" aria-busy="true">
          <div v-for="n in 2" :key="n" class="h-16 rounded-xl bg-ink-100 animate-pulse"></div>
        </div>

        <div
          v-else-if="!compactBookings.length"
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
      </section>
    </div>

    <VendorBookingDetailsModal
      v-model="showBookingModal"
      :booking-id="selectedBookingId"
      @refreshed="fetchMyBookings"
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import VendorOnboardingBanner from '../../components/vendor/VendorOnboardingBanner.vue';
import VendorDashboardFocus from '../../components/vendor/VendorDashboardFocus.vue';
import VendorBookingDetailsModal from '../../components/VendorBookingDetailsModal.vue';
import VendorPaymentModal from '../../components/VendorPaymentModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import {
  boothTypeLabel,
  canVendorProceedToDemoPayment,
  formatBookingDate,
  isTerminalBookingStatus,
  isValidBookingDate,
  siteLabelsForBooking,
  statusBadgeClass,
  statusLabel,
} from '../../utils/bookingDisplay';
import { mapApiNewsToCard } from '../../utils/newsDisplay';
import { resolveVendorOnboardingState } from '../../utils/vendorOnboarding';

const toast = useToast();
const router = useRouter();
const auth = useAuthStore();

const COMPACT_BOOKING_LIMIT = 2;
const MY_TZ = 'Asia/Kuala_Lumpur';

const myBookings = ref([]);
const loadingBookings = ref(false);
const selectedBookingId = ref(null);
const showBookingModal = ref(false);
const showPaymentModal = ref(false);
const paymentBookingId = ref(null);
const paymentInvoiceAmount = ref(null);
const announcements = ref([]);

const userDisplayName = computed(() => auth.user?.name || 'Vendor');

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
  if (action.type === 'view-pass') {
    router.push('/vendor/manage/event-passes');
    return;
  }
  if (action.type === 'view-booking' && action.bookingId) {
    openBookingDetails(action.bookingId);
  }
};

const openBookingDocument = async (bookingId) => {
  if (!bookingId) {
    toast.error('No document is available yet.');
    return;
  }
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
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

const onPaymentSubmitted = async () => {
  await fetchMyBookings();
};

const refreshPrimaryData = async () => {
  await Promise.all([fetchMyBookings(), fetchAnnouncements()]);
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

const compactBookings = computed(() => priorityBookings.value.slice(0, COMPACT_BOOKING_LIMIT));

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
  await Promise.all([fetchMyBookings(), fetchAnnouncements()]);
});

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
