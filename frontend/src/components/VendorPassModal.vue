<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue && pass"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div
          ref="panelRef"
          class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden"
          tabindex="-1"
          @click.stop
        >
          <div class="border-b border-ink-100 bg-gradient-to-r from-brand-600 to-brand-500 px-6 py-5 text-white">
            <button
              type="button"
              class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 hover:bg-white/25"
              aria-label="Close vendor pass"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <p class="text-xs font-bold uppercase tracking-wider text-brand-100">Vendor Event Pass</p>
            <h2 :id="titleId" class="mt-1 text-2xl font-black">Booking #{{ pass.booking_id || pass.id }}</h2>
          </div>

          <div class="p-6 space-y-5">
            <div class="flex flex-wrap gap-2">
              <span :class="passStatusBadgeClass(pass.pass_status)">{{ pass.pass_status_label || 'Pass' }}</span>
              <span v-if="pass.show_qr && isPassQrScannable(pass)" class="ml-badge bg-cyan-100 text-cyan-800">QR Active</span>
              <span v-else-if="pass.show_qr" class="ml-badge bg-ink-100 text-ink-700">QR Inactive</span>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Vendor</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ vendorName }}</dd>
              </div>
              <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Event</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ pass.event_name || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Assigned Booth</dt>
                <dd class="mt-1 font-semibold text-ink-900">
                  <template v-if="pass.show_booth">{{ pass.booth_label || '—' }}</template>
                  <template v-else>{{ pass.pending_message || 'Booth will be assigned after approval' }}</template>
                </dd>
              </div>
              <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Event Date</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ pass.event_date_label || eventDateLabel }}</dd>
              </div>
              <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Event Time</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ formatEventTimeLabel(pass) || eventTimeLabel }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Product</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ pass.product_label || productLabel }}</dd>
              </div>
            </dl>

            <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-4 text-center">
              <template v-if="pass.show_qr && isPassQrScannable(pass)">
                <img
                  :src="qrImageUrl"
                  :alt="`Verification QR for booking ${pass.booking_id || pass.id}`"
                  class="mx-auto h-44 w-44 rounded-lg border border-ink-200 bg-white p-2 object-contain"
                />
                <p class="mt-3 text-xs text-ink-400">Staff can scan this code during the check-in window.</p>
              </template>
              <p v-else class="text-sm font-semibold text-ink-500 py-8">
                {{ passQrDisabledMessage(pass) }}
              </p>
            </div>

            <p class="rounded-xl border border-brand-100 bg-brand-50/80 px-4 py-3 text-sm text-brand-900">
              This pass is valid only for the selected event. QR codes expire after the check-in window closes.
            </p>

            <div class="flex flex-wrap gap-3">
              <button
                v-if="pass.show_qr"
                type="button"
                class="ml-btn-primary"
                @click="downloadPass"
              >
                Download Pass
              </button>
              <button type="button" class="ml-btn-ghost" @click="close">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onUnmounted, nextTick } from 'vue';
import {
  buildQrImageUrl,
  formatEventTimeLabel,
  isPassQrScannable,
  passQrDisabledMessage,
  passStatusBadgeClass,
} from '../utils/vendorPass';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  pass: { type: Object, default: null },
  vendorName: { type: String, default: 'Vendor' },
  boothLabel: { type: String, default: '—' },
  eventDateLabel: { type: String, default: '—' },
  eventTimeLabel: { type: String, default: 'All day event' },
  productLabel: { type: String, default: '—' },
  verifyUrl: { type: String, default: '' },
  qrImageUrl: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'download']);

const panelRef = ref(null);
const titleId = computed(() => {
  const id = props.pass?.booking_id || props.pass?.id;
  return id ? `vendor-pass-modal-${id}` : 'vendor-pass-modal';
});

const qrImageUrl = computed(() => {
  if (props.qrImageUrl) return props.qrImageUrl;
  const id = props.pass?.booking_id || props.pass?.id;
  return id ? buildQrImageUrl(id) : '';
});

const close = () => emit('update:modelValue', false);

const downloadPass = () => {
  emit('download', props.pass?.booking_id || props.pass?.id);
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.modelValue) close();
};

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onEscape);
      await nextTick();
      panelRef.value?.focus();
    } else {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onEscape);
    }
  },
);

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onEscape);
});
</script>
