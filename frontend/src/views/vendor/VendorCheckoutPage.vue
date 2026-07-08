<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50" data-testid="vendor-checkout-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6 space-y-6">
      <div class="flex items-center gap-3">
        <router-link
          to="/dashboard#vendor-my-bookings"
          class="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-700 hover:bg-ink-50"
        >
          ← Back to My Bookings
        </router-link>
      </div>

      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <span class="ml-badge bg-brand-100 text-brand-700">Vendor Checkout</span>
        <h1 class="mt-2 text-3xl font-black text-ink-900 tracking-tight">Complete Payment</h1>
        <p class="mt-2 text-sm text-ink-500">
          This is a demo payment gateway for presentation purposes.
        </p>
      </header>

      <div v-if="loading" class="rounded-3xl border border-white/60 bg-white/80 p-10 text-center text-ink-500">
        Loading checkout details…
      </div>

      <div v-else-if="loadError" class="rounded-3xl border border-rose-200 bg-rose-50/70 p-8 text-center">
        <p class="text-sm font-semibold text-rose-900">{{ loadError }}</p>
        <router-link to="/dashboard#vendor-my-bookings" class="mt-4 inline-flex ml-btn-primary text-sm">
          Back to My Bookings
        </router-link>
      </div>

      <div v-else-if="success" class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-8 text-center" data-testid="vendor-checkout-success">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="mt-4 text-xl font-extrabold text-emerald-900">Payment successful. Your vendor pass is now unlocked.</h2>
        <p class="mt-2 text-sm text-emerald-800">Redirecting you back to My Bookings…</p>
        <router-link
          to="/dashboard#vendor-my-bookings"
          class="mt-6 inline-flex ml-btn-primary text-sm"
          data-testid="vendor-checkout-back-to-bookings"
        >
          Back to My Bookings
        </router-link>
      </div>

      <template v-else-if="booking">
        <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 space-y-5">
          <h2 class="text-lg font-extrabold text-ink-900">Booking Summary</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Booking ID</dt>
              <dd class="mt-1 font-semibold text-ink-900">#{{ booking.id }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Event</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ eventLabel }}</dd>
              <dd class="mt-0.5 text-ink-600">{{ formatBookingDate(booking.booking_date) }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Booth Type</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ boothTypeLabel(booking) }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Assigned Booth</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ boothLabelForBooking(booking) }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 sm:col-span-2">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Product / Category</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ booking.product_category || 'Others' }}</dd>
              <dd v-if="booking.product_details" class="mt-1 text-ink-600 whitespace-pre-line">{{ booking.product_details }}</dd>
            </div>
          </dl>
        </section>

        <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 space-y-4">
          <h2 class="text-lg font-extrabold text-ink-900">Payment Details</h2>
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-100 bg-brand-50/40 px-5 py-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Invoice Amount</p>
              <p class="mt-1 text-2xl font-black text-ink-900">RM {{ formattedAmount }}</p>
            </div>
            <span class="ml-badge bg-amber-100 text-amber-800">{{ booking.invoice?.payment_status || 'Unpaid' }}</span>
          </div>
        </section>

        <section
          v-if="canPay"
          class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5 space-y-5"
          data-testid="vendor-checkout-payment-section"
        >
          <h2 class="text-lg font-extrabold text-ink-900">Choose Payment Method</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label
              v-for="method in paymentMethods"
              :key="method.id"
              class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition"
              :class="selectedMethod === method.id
                ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-500/20'
                : 'border-ink-200 bg-white hover:border-brand-200'"
            >
              <input
                v-model="selectedMethod"
                type="radio"
                name="payment-method"
                :value="method.id"
                class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500"
              />
              <span class="text-sm font-semibold text-ink-900">{{ method.label }}</span>
            </label>
          </div>

          <button
            type="button"
            class="ml-btn-primary w-full sm:w-auto"
            data-testid="vendor-checkout-pay-button"
            :disabled="processing || !selectedMethod"
            @click="submitPayment"
          >
            {{ processing ? 'Processing demo payment…' : `Pay RM ${formattedAmount}` }}
          </button>
        </section>

        <section
          v-else
          class="rounded-3xl border border-amber-200 bg-amber-50/70 p-6 text-sm text-amber-900"
        >
          <p class="font-semibold">Payment is not available for this booking.</p>
          <p class="mt-1">{{ payBlockedMessage }}</p>
          <router-link to="/dashboard#vendor-my-bookings" class="mt-4 inline-flex ml-btn-ghost text-sm">
            Back to My Bookings
          </router-link>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import {
  boothLabelForBooking,
  boothTypeLabel,
  canVendorProceedToDemoPayment,
  formatBookingDate,
} from '../../utils/bookingDisplay';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const auth = useAuthStore();

const booking = ref(null);
const loading = ref(true);
const loadError = ref('');
const processing = ref(false);
const success = ref(false);
const selectedMethod = ref('demo_fpx');

const paymentMethods = [
  { id: 'demo_fpx', label: 'Demo FPX' },
  { id: 'demo_ewallet', label: 'Demo eWallet' },
  { id: 'demo_card', label: 'Demo Card' },
  { id: 'demo_manual_transfer', label: 'Manual Transfer Demo' },
];

const bookingId = computed(() => route.params.bookingId);

const formattedAmount = computed(() => {
  const amount = Number(booking.value?.invoice?.amount ?? 0);
  return amount.toFixed(2);
});

const eventLabel = computed(() =>
  booking.value?.event_label
  || booking.value?.carboot_event?.title
  || booking.value?.carbootEvent?.title
  || 'Carboot Event',
);

const canPay = computed(() => canVendorProceedToDemoPayment(booking.value));

const payBlockedMessage = computed(() => {
  if (!booking.value) return '';
  if (booking.value.approval_status !== 'Approved') {
    return 'Only approved bookings can proceed to payment.';
  }
  if (booking.value.invoice?.payment_status === 'Paid') {
    return 'This booking has already been paid.';
  }
  if (booking.value.invoice?.payment_status === 'Pending Verification') {
    return 'Your payment proof is awaiting CMart verification.';
  }
  return 'Please contact CMart staff if you need help with this booking.';
});

const loadBooking = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await api.get(`/vendor/bookings/${bookingId.value}`);
    booking.value = data;
    if (!canVendorProceedToDemoPayment(data) && data.invoice?.payment_status === 'Paid') {
      router.replace('/dashboard#vendor-my-bookings');
      toast.info('This booking is already paid.');
    }
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Unable to load checkout details.';
  } finally {
    loading.value = false;
  }
};

const submitPayment = async () => {
  if (!canPay.value || !selectedMethod.value) return;

  processing.value = true;
  try {
    await new Promise((resolve) => setTimeout(resolve, 1200));
    const { data } = await api.post(`/vendor/bookings/${bookingId.value}/demo-payment`, {
      payment_method: selectedMethod.value,
    });
    booking.value = data.booking;
    success.value = true;
    toast.success(data.message || 'Payment successful. Your vendor pass is now unlocked.');
    setTimeout(() => {
      router.push('/dashboard#vendor-my-bookings');
    }, 2500);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to complete demo payment.');
  } finally {
    processing.value = false;
  }
};

onMounted(loadBooking);
</script>
