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
        v-if="modelValue"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="withdraw-modal-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <Transition
          appear
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
        >
          <div
            v-if="modelValue"
            data-testid="withdraw-booking-modal"
            class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
            @click.stop
          >
            <div class="border-b border-ink-100 px-5 py-4">
              <h3 id="withdraw-modal-title" class="text-lg font-extrabold text-ink-900">
                Withdraw booking?
              </h3>
            </div>

            <div class="px-5 py-4 space-y-4">
              <p
                class="text-sm leading-relaxed"
                :class="requiresAcknowledgement ? 'font-semibold text-rose-800' : 'text-ink-700'"
                data-testid="withdraw-booking-warning"
              >
                {{ warningMessage }}
              </p>

              <p v-if="!requiresAcknowledgement" class="text-sm font-semibold text-rose-700">
                This action cannot be undone from your dashboard.
              </p>

              <label
                v-if="requiresAcknowledgement"
                class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/70 p-3 cursor-pointer"
                data-testid="withdraw-no-refund-acknowledgement-label"
              >
                <input
                  v-model="acknowledged"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500"
                  data-testid="withdraw-no-refund-acknowledgement"
                  :disabled="submitting"
                />
                <span class="text-sm text-rose-900">
                  Saya faham bahawa bayaran tidak akan dipulangkan dan tapak akan dibuka semula kepada vendor lain.
                </span>
              </label>

              <div>
                <label for="withdrawal-reason" class="ml-label">Reason for withdrawal (optional)</label>
                <textarea
                  id="withdrawal-reason"
                  v-model="reason"
                  data-testid="withdrawal-reason"
                  rows="3"
                  class="ml-input"
                  placeholder="Example: Schedule conflict, wrong event date, changed product plan..."
                  :disabled="submitting"
                />
              </div>

              <p v-if="errorMessage" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                {{ errorMessage }}
              </p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-ink-100 px-5 py-4">
              <button
                type="button"
                class="ml-btn-ghost"
                data-testid="withdraw-booking-cancel"
                :disabled="submitting"
                @click="close"
              >
                Keep booking
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed"
                data-testid="withdraw-booking-confirm"
                :disabled="submitting || !canConfirm"
                @click="confirmWithdraw"
              >
                {{ submitting ? 'Withdrawing…' : 'Yes, withdraw booking' }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
  requiresNoRefundAcknowledgement,
  withdrawalWarningMessage,
} from '../utils/bookingDisplay';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  booking: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const reason = ref('');
const acknowledged = ref(false);
const submitting = ref(false);
const errorMessage = ref('');

const requiresAcknowledgement = computed(() => requiresNoRefundAcknowledgement(props.booking));
const warningMessage = computed(() => withdrawalWarningMessage(props.booking));
const canConfirm = computed(() => !requiresAcknowledgement.value || acknowledged.value);

const close = () => {
  if (submitting.value) return;
  emit('update:modelValue', false);
};

const confirmWithdraw = () => {
  if (!canConfirm.value || submitting.value) return;
  errorMessage.value = '';
  emit('confirm', {
    withdrawal_reason: reason.value.trim(),
    acknowledge_no_refund: requiresAcknowledgement.value ? true : undefined,
    setSubmitting: (value) => { submitting.value = value; },
    setError: (message) => { errorMessage.value = message; },
    close: () => emit('update:modelValue', false),
  });
};

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      reason.value = '';
      acknowledged.value = false;
      errorMessage.value = '';
      submitting.value = false;
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  },
);
</script>
