<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50">
    <AppNavbar />

    <div class="max-w-2xl mx-auto py-10 px-4 sm:px-6">
      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <h1 class="text-2xl font-black text-ink-900">Vendor Pass Verification</h1>
        <p class="mt-1 text-sm text-ink-500">Booking #{{ bookingId }}</p>

        <div v-if="loading" class="mt-8 text-sm text-ink-500">Verifying pass…</div>

        <div v-else-if="error" class="mt-8 rounded-2xl border border-rose-200 bg-rose-50/70 p-6 text-center">
          <p class="font-semibold text-rose-900">{{ error }}</p>
        </div>

        <template v-else-if="result">
          <div class="mt-6 flex flex-wrap gap-2">
            <span :class="result.valid ? 'ml-badge bg-emerald-100 text-emerald-800' : 'ml-badge bg-rose-100 text-rose-800'">
              {{ result.valid ? 'Valid Pass' : 'Invalid Pass' }}
            </span>
            <span v-if="result.pass" :class="passStatusBadgeClass(result.pass.pass_status)">
              {{ result.pass.pass_status_label }}
            </span>
          </div>

          <p v-if="result.reason" class="mt-4 text-sm font-semibold text-rose-700">{{ result.reason }}</p>

          <dl v-if="result.pass" class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Vendor</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.vendor?.name || '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Event</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.pass.event_name }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Booth</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.pass.booth_label || '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Product</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.pass.product_label }}</dd>
            </div>
          </dl>

          <div class="mt-8 flex flex-wrap gap-3">
            <button
              type="button"
              class="ml-btn-primary"
              :disabled="!result.valid || checkingIn"
              @click="checkIn"
            >
              {{ checkingIn ? 'Checking in…' : 'Check In Vendor' }}
            </button>
            <router-link to="/admin" class="ml-btn-ghost">Back to Workspace</router-link>
          </div>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import api from '../../services/api';
import { passStatusBadgeClass } from '../../utils/vendorPass';

const route = useRoute();
const toast = useToast();

const bookingId = route.params.bookingId;
const loading = ref(true);
const checkingIn = ref(false);
const error = ref('');
const result = ref(null);

const verify = async () => {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/staff/bookings/${bookingId}/verify`);
    result.value = data;
  } catch (err) {
    if (err.response?.data) {
      result.value = err.response.data;
    } else {
      error.value = 'Unable to verify this vendor pass.';
    }
  } finally {
    loading.value = false;
  }
};

const checkIn = async () => {
  checkingIn.value = true;
  try {
    const { data } = await api.post(`/staff/bookings/${bookingId}/check-in`);
    toast.success(data.message || 'Vendor checked in.');
    await verify();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Check-in failed.');
  } finally {
    checkingIn.value = false;
  }
};

onMounted(verify);
</script>
