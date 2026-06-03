<template>
  <div class="min-h-screen bg-ink-50 py-12 px-4">
    <div class="max-w-6xl mx-auto">
      <router-link to="/" class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6">
        <span class="mr-1">←</span> Back to Carboot@CMart
      </router-link>

      <div class="ml-card mb-6">
        <div class="mb-6">
          <span class="ml-badge bg-brand-100 text-brand-700">Vendor Booking</span>
          <h1 class="mt-2 text-2xl font-extrabold text-ink-900 tracking-tight">
            Book your Carboot space
          </h1>
          <p class="text-sm text-ink-500 mt-1">
            Reserve your space for the next Carboot@CMart event. Approval takes 3-5 working days.
          </p>
        </div>

        <form @submit.prevent="submitBooking" class="space-y-4">
          <div>
            <label class="ml-label">Your name</label>
            <input v-model="userName" type="text" required class="ml-input" placeholder="e.g. Ahmad bin Ali" />
          </div>

          <div>
            <label class="ml-label">Product category</label>
            <select v-model="bookingForm.product_category" required class="ml-input">
              <option disabled value="">Select a category</option>
              <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
          </div>

          <div>
            <div class="flex items-center gap-2 mb-1">
              <label class="ml-label !mb-0">Specific products you will sell</label>
              
              <div class="group relative flex flex-col items-center">
                <button type="button" class="flex h-5 w-5 items-center justify-center rounded-full bg-ink-200 text-xs font-bold text-ink-600 hover:bg-brand-100 hover:text-brand-700 focus:outline-none">
                  i
                </button>
                
                <div class="absolute bottom-full mb-2 hidden w-56 flex-col items-center group-hover:flex group-focus:flex group-focus-within:flex">
                  <span class="relative z-10 rounded-lg bg-ink-900 p-2.5 text-xs leading-relaxed text-white shadow-lg text-center">
                    e.g., Ayam Gunting, Bundle T-shirt, Ramen. 
                    <br/><br/>
                    Please be specific to help us process your approval faster!
                  </span>
                  <div class="-mt-2 h-3 w-3 rotate-45 bg-ink-900"></div>
                </div>
              </div>
            </div>

            <input
              v-model="bookingForm.product_details"
              type="text"
              required
              class="ml-input"
              placeholder="e.g., Ayam Gunting, Bundle T-shirt..."
            />
          </div>

          <div>
            <label class="ml-label">Select space size</label>
            <select v-model="bookingForm.space_id" @change="updatePrice" required class="ml-input">
              <option disabled value="">Please select one</option>
              <option v-for="space in availableSpaces" :key="space.id" :value="space.id">
                {{ space.space_size }} — RM {{ space.price.toFixed(2) }}
              </option>
            </select>
          </div>

          <div class="bg-brand-50 border border-brand-200 rounded-xl p-4 flex items-center justify-between">
            <span class="text-sm font-semibold text-brand-800">Total price</span>
            <span class="text-2xl font-extrabold text-brand-700">RM {{ currentPrice.toFixed(2) }}</span>
          </div>

          <div>
            <label class="ml-label">Booking date</label>
            <input v-model="bookingForm.booking_date" type="date" required class="ml-input" />
          </div>

          <button type="submit" class="ml-btn-primary w-full" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit booking' }}
          </button>

          <p class="text-xs text-ink-500 text-center">
            By submitting, you agree to Carboot@CMart vendor terms. You will receive a WhatsApp confirmation after review.
          </p>
        </form>
      </div>

      <div class="ml-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
          <div>
            <h2 class="text-xl font-extrabold text-ink-900">My Bookings</h2>
            <p class="text-sm text-ink-500">Track your booking through the Carboot@CMart approval pipeline.</p>
          </div>
          <button class="ml-btn-ghost" @click="fetchMyBookings" :disabled="loadingBookings">
            {{ loadingBookings ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>

        <div v-if="!myBookings.length" class="rounded-xl border border-dashed border-ink-300 p-8 text-center text-ink-500">
          No booking records are currently available.
        </div>

        <div v-else class="space-y-5">
          <article
            v-for="booking in myBookings"
            :key="booking.id"
            class="rounded-2xl border border-ink-200 bg-white p-5 shadow-sm"
          >
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div>
                <div class="flex items-center gap-2">
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

              <button class="ml-btn-ghost" @click="viewPdf(booking.id)">
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
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from './services/api';
import { useAuthStore } from './stores/auth';
import { PRODUCT_CATEGORIES } from './constants/productCategories';

const toast = useToast();
const auth = useAuthStore();

const bookingForm = reactive({
  space_id: '',
  booking_date: '',
  product_category: '',
  product_details: '',
});

const userName = ref('');
const currentPrice = ref(0);
const availableSpaces = ref([]);
const submitting = ref(false);
const myBookings = ref([]);
const loadingBookings = ref(false);
const resubmitDates = reactive({});

const pipelineSteps = [
  { index: 1, status: 'Pending_Staff', label: 'Staff Review' },
  { index: 2, status: 'Pending_Boss', label: 'Boss Review' },
  { index: 3, status: 'Approved', label: 'Approved' },
];

onMounted(async () => {
  userName.value = auth.user?.name || '';

  try {
    const { data } = await api.get('/spaces');
    const list = Array.isArray(data) ? data : (data.data ?? []);
    availableSpaces.value = list.map(s => ({ ...s, price: Number(s.price) }));
  } catch (e) {
    console.warn('503 Service Unavailable: Unable to retrieve space data from the API.', e?.message);
    availableSpaces.value = [
      { id: 1, space_size: 'Standard (1 Parking Lot)', price: 30.00 },
      { id: 2, space_size: 'Large (2 Parking Lots)',   price: 50.00 },
    ];
  }

  await fetchMyBookings();
});

const updatePrice = () => {
  const space = availableSpaces.value.find(s => s.id === bookingForm.space_id);
  currentPrice.value = space ? space.price : 0;
};

const resetBookingForm = () => {
  bookingForm.space_id = '';
  bookingForm.booking_date = '';
  bookingForm.product_category = '';
  bookingForm.product_details = '';
  currentPrice.value = 0;
};

const submitBooking = async () => {
  submitting.value = true;
  try {
    const { data } = await api.post('/bookings', {
      space_id: bookingForm.space_id,
      booking_date: bookingForm.booking_date,
      product_category: bookingForm.product_category,
      product_details: bookingForm.product_details,
    });
    toast.success(data.message || '201 Created: Booking submitted successfully.');
    userName.value = '';
    resetBookingForm();
    await fetchMyBookings();
  } catch (e) {
    console.error('500 Internal Server Error: Unable to communicate with the API.', e);
    toast.error('500 Internal Server Error: Unable to communicate with the API.');
  } finally {
    submitting.value = false;
  }
};

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
</script>
