<template>
  <div class="min-h-screen bg-ink-50">
    <AppNavbar variant="vendor" />
    <div class="py-12 px-4">
    <div class="max-w-2xl mx-auto">
      <router-link to="/dashboard" class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6">
        <span class="mr-1">←</span> Back to My Dashboard
      </router-link>

      <div class="ml-card">
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
            <label class="ml-label">Number of Tapak (Parking Lots)</label>
            <p class="text-xs text-ink-500 mb-2">1 Tapak = RM 20.00</p>

            <div class="flex items-center justify-center gap-3 sm:gap-4 w-full max-w-xs mx-auto sm:mx-0">
              <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-ink-200 bg-white text-xl font-bold text-ink-700 shadow-sm transition hover:bg-ink-50 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="tapakQuantity <= 1"
                aria-label="Decrease tapak quantity"
                @click="decreaseTapak"
              >
                −
              </button>

              <div class="flex min-w-[4.5rem] flex-1 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5">
                <span class="text-2xl font-extrabold text-brand-800 tabular-nums">{{ tapakQuantity }}</span>
              </div>

              <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-ink-200 bg-white text-xl font-bold text-ink-700 shadow-sm transition hover:bg-ink-50"
                aria-label="Increase tapak quantity"
                @click="increaseTapak"
              >
                +
              </button>
            </div>

            <p class="mt-4 text-lg sm:text-xl font-extrabold text-brand-700 text-center sm:text-left">
              Total Price: RM {{ totalPrice }}.00
            </p>
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
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { PRODUCT_CATEGORIES } from '../../constants/productCategories';

const TAPAK_UNIT_PRICE = 20;

const toast = useToast();
const auth = useAuthStore();
const router = useRouter();

const bookingForm = reactive({
  booking_date: '',
  product_category: '',
  product_details: '',
});

const userName = ref(auth.user?.name || '');
const tapakQuantity = ref(1);
const submitting = ref(false);

const totalPrice = computed(() => tapakQuantity.value * TAPAK_UNIT_PRICE);

onMounted(() => {
  userName.value = auth.user?.name || '';
});

const decreaseTapak = () => {
  if (tapakQuantity.value > 1) {
    tapakQuantity.value -= 1;
  }
};

const increaseTapak = () => {
  tapakQuantity.value += 1;
};

const resetBookingForm = () => {
  bookingForm.booking_date = '';
  bookingForm.product_category = '';
  bookingForm.product_details = '';
  tapakQuantity.value = 1;
};

const submitBooking = async () => {
  submitting.value = true;
  try {
    const { data } = await api.post('/bookings', {
      booking_date: bookingForm.booking_date,
      product_category: bookingForm.product_category,
      product_details: bookingForm.product_details,
      tapak_quantity: tapakQuantity.value,
      total_price: totalPrice.value,
    });
    toast.success(data.message || '201 Created: Booking submitted successfully.');
    resetBookingForm();
    router.push('/dashboard');
  } catch (e) {
    console.error('500 Internal Server Error: Unable to communicate with the API.', e);
    toast.error(e.response?.data?.message || '500 Internal Server Error: Unable to communicate with the API.');
  } finally {
    submitting.value = false;
  }
};
</script>
