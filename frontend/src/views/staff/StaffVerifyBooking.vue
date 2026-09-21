<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50">
    <AppNavbar />

    <div class="max-w-2xl mx-auto py-10 px-4 sm:px-6">
      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <h1 class="text-2xl font-black text-ink-900">{{ t('staff.verify.title') }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ t('staff.verify.bookingLabel', { ref: formatBookingReference(bookingId) }) }}</p>

        <div v-if="loading" class="mt-8 text-sm text-ink-500">{{ t('staff.verify.verifying') }}</div>

        <div v-else-if="error" class="mt-8 rounded-2xl border border-rose-200 bg-rose-50/70 p-6 text-center">
          <p class="font-semibold text-rose-900">{{ error }}</p>
        </div>

        <template v-else-if="result">
          <div class="mt-6 flex flex-wrap gap-2">
            <span :class="result.valid ? 'ml-badge bg-emerald-100 text-emerald-800' : 'ml-badge bg-rose-100 text-rose-800'">
              {{ result.valid ? t('staff.verify.validPass') : t('staff.verify.invalidPass') }}
            </span>
            <span v-if="result.pass" :class="passStatusBadgeClass(result.pass.pass_status)">
              {{ result.pass.pass_status_label }}
            </span>
          </div>

          <p v-if="result.reason" class="mt-4 text-sm font-semibold text-rose-700">{{ result.reason }}</p>

          <dl v-if="result.pass" class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('staff.verify.vendor') }}</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.vendor?.name || t('common.none') }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('staff.verify.event') }}</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.pass.event_name }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('staff.verify.booth') }}</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ result.pass.booth_label || t('common.none') }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('staff.verify.product') }}</dt>
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
              {{ checkingIn ? t('staff.verify.checkingIn') : t('staff.verify.checkIn') }}
            </button>
            <router-link to="/admin" class="ml-btn-ghost">{{ t('staff.verify.backToWorkspace') }}</router-link>
          </div>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import api from '../../services/api';
import { formatBookingReference } from '../../utils/bookingDisplay';
import { passStatusBadgeClass } from '../../utils/vendorPass';

const { t } = useI18n();
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
    const { data } = await api.get(`/organizer/bookings/${bookingId}/verify`);
    result.value = data;
  } catch (err) {
    if (err.response?.data) {
      result.value = err.response.data;
    } else {
      error.value = t('staff.verify.unableVerify');
    }
  } finally {
    loading.value = false;
  }
};

const checkIn = async () => {
  checkingIn.value = true;
  try {
    const { data } = await api.post(`/organizer/bookings/${bookingId}/check-in`);
    toast.success(data.message || t('staff.verify.checkedIn'));
    await verify();
  } catch (err) {
    toast.error(err.response?.data?.message || t('staff.verify.checkInFailed'));
  } finally {
    checkingIn.value = false;
  }
};

onMounted(verify);
</script>
