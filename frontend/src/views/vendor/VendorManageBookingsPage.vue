<template>
  <VendorPageShell
    title="My Bookings"
    subtitle="Upcoming first. Open details when you need the full timeline."
    test-id="vendor-manage-bookings-root"
  >
    <template #actions>
      <router-link
        to="/vendor-booking"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 min-h-[44px] text-[15px] font-bold text-white shadow-md shadow-brand-500/20 hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 transition"
      >
        {{ validBookings.length ? 'Book a Space' : 'Start Vendor Booking' }}
      </router-link>
      <button
        type="button"
        class="ml-btn-ghost min-h-[44px]"
        :disabled="loadingBookings"
        @click="fetchMyBookings"
      >
        {{ loadingBookings ? 'Refreshing…' : 'Refresh' }}
      </button>
    </template>

    <section class="rounded-2xl border border-ink-100 bg-white p-5 sm:p-6 shadow-sm" data-testid="my-bookings-root">
      <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input
          v-model="bookingSearchQuery"
          type="search"
          placeholder="Search bookings…"
          data-testid="booking-search"
          class="w-full sm:max-w-sm rounded-xl border border-ink-200 bg-white px-4 py-2.5 min-h-[44px] text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
        />
      </div>

      <div class="flex flex-wrap gap-2 mb-4">
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

      <div v-if="loadingBookings" class="space-y-2" aria-busy="true">
        <div v-for="n in 3" :key="n" class="h-16 rounded-xl bg-ink-100 animate-pulse"></div>
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

      <div
        v-else-if="!filteredBookings.length"
        class="rounded-xl border border-dashed border-ink-200 px-4 py-6 text-center text-sm text-ink-500"
      >
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
                  type="button"
                  class="ml-btn-ghost text-sm min-h-[44px]"
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
    </section>

    <VendorBookingDetailsModal
      v-model="showBookingModal"
      :booking-id="selectedBookingId"
      @refreshed="fetchMyBookings"
    />
  </VendorPageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import VendorPageShell from '../../components/vendor/VendorPageShell.vue';
import VendorBookingDetailsModal from '../../components/VendorBookingDetailsModal.vue';
import api from '../../services/api';
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

const toast = useToast();

const myBookings = ref([]);
const loadingBookings = ref(false);
const bookingSearchQuery = ref('');
const selectedBookingStatus = ref('all');
const selectedBookingId = ref(null);
const showBookingModal = ref(false);

const MY_TZ = 'Asia/Kuala_Lumpur';
const todayKey = () => new Date().toLocaleDateString('en-CA', { timeZone: MY_TZ });

const bookingDateKey = (booking) => {
  const raw = String(booking?.booking_date || '').slice(0, 10);
  return isValidBookingDate(raw) ? raw : null;
};

const validBookings = computed(() =>
  myBookings.value.filter((booking) => isValidBookingDate(booking.booking_date)),
);

const priorityBookings = computed(() => {
  const today = todayKey();
  return [...validBookings.value].sort((a, b) => {
    const aKey = bookingDateKey(a) || '9999-99-99';
    const bKey = bookingDateKey(b) || '9999-99-99';
    const aUpcoming = aKey >= today;
    const bUpcoming = bKey >= today;
    if (aUpcoming !== bUpcoming) return aUpcoming ? -1 : 1;
    if (aKey !== bKey) return aUpcoming ? aKey.localeCompare(bKey) : bKey.localeCompare(aKey);
    return (b.id ?? 0) - (a.id ?? 0);
  });
});

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

const filteredBookings = computed(() => {
  const query = String(bookingSearchQuery.value ?? '').toLowerCase().trim();
  return priorityBookings.value.filter(
    (booking) =>
      matchesStatusFilter(booking, selectedBookingStatus.value)
      && bookingMatchesSearch(booking, query),
  );
});

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

onMounted(fetchMyBookings);
</script>
