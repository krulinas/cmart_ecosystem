<template>
  <section
    id="vendor-event-passes"
    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5"
    data-testid="vendor-event-passes-root"
  >
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-extrabold text-ink-900">My Event Passes</h2>
        <p class="mt-1 text-sm text-ink-500 max-w-xl">
          Event-specific check-in passes tied to your approved bookings. QR codes are only valid during the event check-in window.
        </p>
      </div>
      <button type="button" class="ml-btn-ghost text-sm shrink-0" :disabled="loading" @click="loadPasses">
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading" class="rounded-2xl border border-ink-100 bg-ink-50/60 p-8 animate-pulse space-y-4">
      <div class="h-4 w-1/3 rounded bg-ink-100"></div>
      <div class="h-24 rounded-xl bg-ink-100"></div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-8 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load your event passes.</p>
      <button type="button" class="mt-3 ml-btn-ghost text-sm" @click="loadPasses">Try Again</button>
    </div>

    <div
      v-else-if="!upcomingPasses.length && !archivedPasses.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/60 p-8 sm:p-10 text-center"
    >
      <h3 class="text-lg font-bold text-ink-900">No active event pass yet</h3>
      <p class="mt-2 text-sm text-ink-500 max-w-md mx-auto">
        Book a space to generate your vendor pass.
      </p>
      <router-link to="/vendor-booking" class="mt-4 inline-flex ml-btn-primary text-sm">Book a Space</router-link>
    </div>

    <template v-else>
      <div v-if="upcomingPasses.length > 1" class="mb-5">
        <label class="ml-label">Select event pass</label>
        <select v-model="selectedPassId" class="ml-input max-w-xl">
          <option v-for="pass in upcomingPasses" :key="pass.booking_id" :value="pass.booking_id">
            #{{ pass.booking_id }} · {{ pass.event_name }} · {{ pass.event_date_label }}
          </option>
        </select>
      </div>

      <div v-if="selectedPass" class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        <div class="xl:col-span-3 rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white p-5 sm:p-6 shadow-inner">
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <span :class="passStatusBadgeClass(selectedPass.pass_status)">{{ selectedPass.pass_status_label }}</span>
            <span class="ml-badge bg-brand-100 text-brand-800">Booking #{{ selectedPass.booking_id }}</span>
          </div>

          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Event</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ selectedPass.event_name }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Event Date</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ selectedPass.event_date_label }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Event Time</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ formatEventTimeLabel(selectedPass) }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Assigned Booth</dt>
              <dd class="mt-1 font-semibold text-ink-900">
                <template v-if="selectedPass.show_booth">{{ selectedPass.booth_label || '—' }}</template>
                <template v-else>{{ selectedPass.pending_message || 'Booth will be assigned after approval' }}</template>
              </dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Booth Type</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ selectedPass.booth_type_label }}</dd>
            </div>
            <div>
              <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">Product / Category</dt>
              <dd class="mt-1 font-semibold text-ink-900">{{ selectedPass.product_label }}</dd>
            </div>
          </dl>

          <p v-if="selectedPass.checked_in_at" class="mt-4 text-sm text-indigo-700 font-semibold">
            Checked in at {{ formatCheckedIn(selectedPass.checked_in_at) }}
          </p>

          <div class="mt-6 flex flex-wrap gap-3">
            <button
              v-if="selectedPass.show_qr"
              type="button"
              class="ml-btn-primary"
              data-testid="vendor-pass-button"
              :data-booking-id="selectedPass.booking_id"
              @click="openPassModal"
            >
              View Full Pass
            </button>
            <button
              v-if="selectedPass.show_qr"
              type="button"
              class="ml-btn-ghost"
              @click="$emit('download-pass', selectedPass.booking_id)"
            >
              Download Pass
            </button>
          </div>
        </div>

        <div class="xl:col-span-2 rounded-2xl border border-ink-200 bg-white p-5 text-center shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-ink-500">Verification QR</p>

          <template v-if="selectedPass.show_qr && isPassQrScannable(selectedPass)">
            <img
              :src="qrImageUrl"
              :alt="`Verification QR for booking ${selectedPass.booking_id}`"
              class="mx-auto mt-4 h-44 w-44 rounded-xl border border-ink-100 bg-white p-2 object-contain"
            />
            <p class="mt-3 text-xs text-ink-400">Valid during the event check-in window only.</p>
          </template>

          <div
            v-else
            class="mx-auto mt-4 flex h-44 w-44 items-center justify-center rounded-xl border border-dashed border-ink-200 bg-ink-50 px-4"
          >
            <p class="text-xs font-semibold text-ink-500">
              {{ passQrDisabledMessage(selectedPass) }}
            </p>
          </div>

          <p v-if="selectedPass.show_qr && !isPassQrScannable(selectedPass)" class="mt-3 text-xs text-rose-600 font-semibold">
            QR unavailable
          </p>
        </div>
      </div>

      <div v-else-if="upcomingPasses.length" class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/60 p-8 text-center text-ink-500">
        Select an event pass to view details.
      </div>

      <div v-if="archivedPasses.length" class="mt-8 border-t border-ink-100 pt-6">
        <button
          type="button"
          class="flex w-full items-center justify-between text-left"
          @click="archivedExpanded = !archivedExpanded"
        >
          <div>
            <h3 class="text-base font-extrabold text-ink-900">Past / Archived Passes</h3>
            <p class="text-sm text-ink-500">{{ archivedPasses.length }} completed or expired pass{{ archivedPasses.length === 1 ? '' : 'es' }}</p>
          </div>
          <span class="text-sm font-semibold text-brand-700">{{ archivedExpanded ? 'Hide' : 'Show' }}</span>
        </button>

        <ul v-if="archivedExpanded" class="mt-4 space-y-3">
          <li
            v-for="pass in archivedPasses"
            :key="pass.booking_id"
            class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
          >
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-ink-900">#{{ pass.booking_id }} · {{ pass.event_name }}</span>
                <span :class="passStatusBadgeClass(pass.pass_status)">{{ pass.pass_status_label }}</span>
              </div>
              <p class="mt-1 text-sm text-ink-500">{{ pass.event_date_label }} · {{ pass.product_label }}</p>
            </div>
            <button type="button" class="ml-btn-ghost text-sm shrink-0" @click="viewArchivedPass(pass)">
              View Details
            </button>
          </li>
        </ul>
      </div>
    </template>

    <VendorPassModal
      v-model="showPassModal"
      :pass="modalPass"
      :vendor-name="vendorName"
      @download="$emit('download-pass', $event)"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import VendorPassModal from './VendorPassModal.vue';
import api from '../services/api';
import {
  buildQrImageUrl,
  formatEventTimeLabel,
  isPassQrScannable,
  passQrDisabledMessage,
  passStatusBadgeClass,
} from '../utils/vendorPass';

defineProps({
  vendorName: { type: String, default: 'Vendor' },
});

defineEmits(['download-pass']);

const loading = ref(false);
const loadError = ref(false);
const upcomingPasses = ref([]);
const archivedPasses = ref([]);
const selectedPassId = ref(null);
const archivedExpanded = ref(false);
const showPassModal = ref(false);
const modalPass = ref(null);

const selectedPass = computed(() => {
  const all = [...upcomingPasses.value, ...archivedPasses.value];
  return all.find((pass) => pass.booking_id === selectedPassId.value) || upcomingPasses.value[0] || null;
});

const qrImageUrl = computed(() =>
  selectedPass.value?.booking_id ? buildQrImageUrl(selectedPass.value.booking_id) : '',
);

const formatCheckedIn = (iso) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-GB', {
    timeZone: 'Asia/Kuala_Lumpur',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

const openPassModal = () => {
  modalPass.value = selectedPass.value;
  showPassModal.value = true;
};

const viewArchivedPass = (pass) => {
  selectedPassId.value = pass.booking_id;
  modalPass.value = pass;
  showPassModal.value = true;
};

const loadPasses = async () => {
  loading.value = true;
  loadError.value = false;

  try {
    const { data } = await api.get('/vendor/event-passes');
    upcomingPasses.value = Array.isArray(data?.upcoming) ? data.upcoming : [];
    archivedPasses.value = Array.isArray(data?.archived) ? data.archived : [];

    if (data?.default_pass_id) {
      selectedPassId.value = data.default_pass_id;
    } else if (upcomingPasses.value.length) {
      selectedPassId.value = upcomingPasses.value[0].booking_id;
    } else {
      selectedPassId.value = null;
    }
  } catch (error) {
    console.error('Unable to load vendor event passes:', error);
    loadError.value = true;
    upcomingPasses.value = [];
    archivedPasses.value = [];
  } finally {
    loading.value = false;
  }
};

watch(upcomingPasses, (passes) => {
  if (!passes.length) return;
  if (!passes.some((pass) => pass.booking_id === selectedPassId.value)) {
    selectedPassId.value = passes[0].booking_id;
  }
});

onMounted(loadPasses);

defineExpose({ loadPasses });
</script>
