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
        v-if="modelValue && bookingId"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-modal-title"
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
            data-testid="invoice-payment-modal"
            class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
            @click.stop
          >
            <div class="border-b border-ink-100 px-5 py-4">
              <h3 id="payment-modal-title" class="text-lg font-extrabold text-ink-900">
                Submit payment proof
              </h3>
              <p v-if="amount != null" class="mt-1 text-sm text-ink-500">
                Booking #{{ bookingId }} · RM {{ formattedAmount }}
              </p>
            </div>

            <div class="px-5 py-4 space-y-4" data-testid="invoice-payment-section">
              <p class="text-sm text-ink-700 leading-relaxed">
                Upload your transfer receipt or payment screenshot. The Carboot Organizer will verify your payment before your booth pass is released.
              </p>

              <div>
                <label for="payment-proof-input" class="ml-label">Payment proof (image)</label>
                <input
                  id="payment-proof-input"
                  ref="fileInputRef"
                  type="file"
                  accept="image/png,image/jpeg,image/jpg,image/webp"
                  data-testid="payment-proof-input"
                  class="mt-2 block w-full text-sm text-ink-700 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
                  :disabled="submitting"
                  @change="onFileChange"
                />
                <p v-if="selectedFileName" class="mt-2 text-xs text-ink-500">Selected: {{ selectedFileName }}</p>
              </div>

              <p v-if="errorMessage" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                {{ errorMessage }}
              </p>

              <p
                v-if="successMessage"
                data-testid="payment-success-message"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
              >
                {{ successMessage }}
              </p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-ink-100 px-5 py-4">
              <button
                type="button"
                class="ml-btn-ghost"
                data-testid="payment-cancel-button"
                :disabled="submitting"
                @click="close"
              >
                Cancel
              </button>
              <button
                type="button"
                class="ml-btn-primary"
                data-testid="payment-submit-button"
                :disabled="submitting || !selectedFile"
                @click="submitPayment"
              >
                {{ submitting ? 'Submitting…' : 'Submit payment' }}
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
import api from '../services/api';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  bookingId: { type: [Number, String], default: null },
  amount: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'submitted']);

const fileInputRef = ref(null);
const selectedFile = ref(null);
const selectedFileName = ref('');
const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const formattedAmount = computed(() => Number(props.amount ?? 0).toFixed(2));

const resetForm = () => {
  selectedFile.value = null;
  selectedFileName.value = '';
  errorMessage.value = '';
  successMessage.value = '';
  submitting.value = false;
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
};

const close = () => {
  if (submitting.value) return;
  emit('update:modelValue', false);
};

const onFileChange = (event) => {
  const file = event.target.files?.[0] ?? null;
  selectedFile.value = file;
  selectedFileName.value = file?.name ?? '';
  errorMessage.value = '';
};

const submitPayment = async () => {
  if (!selectedFile.value || !props.bookingId) {
    errorMessage.value = 'Please choose a payment proof image before submitting.';
    return;
  }

  submitting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const formData = new FormData();
    formData.append('payment_proof', selectedFile.value);

    const { data } = await api.post(`/vendor/bookings/${props.bookingId}/submit-payment`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    successMessage.value = data.message || 'Payment proof submitted successfully.';
    emit('submitted', data);
    setTimeout(() => {
      emit('update:modelValue', false);
    }, 600);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to submit payment proof.';
  } finally {
    submitting.value = false;
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      resetForm();
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
      resetForm();
    }
  },
);
</script>
