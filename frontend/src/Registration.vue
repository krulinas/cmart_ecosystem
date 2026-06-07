<template>
  <div class="min-h-screen bg-ink-50 py-12 px-4">
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
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from './services/api';
import { useAuthStore } from './stores/auth';
import { PRODUCT_CATEGORIES } from './constants/productCategories';

const toast = useToast();
const auth = useAuthStore();
const router = useRouter();

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
    resetBookingForm();
    router.push('/dashboard');
  } catch (e) {
    console.error('500 Internal Server Error: Unable to communicate with the API.', e);
    toast.error('500 Internal Server Error: Unable to communicate with the API.');
  } finally {
    submitting.value = false;
  }
};
</script>
