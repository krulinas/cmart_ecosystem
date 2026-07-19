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
        aria-labelledby="item-reservation-confirm-title"
        data-testid="item-reservation-confirm-modal"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div
          class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden max-h-[90vh] overflow-y-auto"
          @click.stop
        >
          <div class="border-b border-ink-100 px-5 py-4">
            <h3 id="item-reservation-confirm-title" class="text-lg font-extrabold text-ink-900">
              {{ successReservation ? 'Reservation recorded' : 'Reserve this item?' }}
            </h3>
          </div>

          <div v-if="successReservation" class="px-5 py-5 space-y-4" data-testid="item-reservation-success">
            <p class="text-sm text-emerald-800 font-semibold">
              Your reservation hold is recorded. Collect the item in person at the event.
            </p>
            <dl class="grid grid-cols-1 gap-3 text-sm">
              <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Reference</dt>
                <dd class="mt-1 font-bold text-ink-900" data-testid="reservation-success-reference">
                  {{ successReservation.public_reference }}
                </dd>
              </div>
              <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Status</dt>
                <dd class="mt-1 font-semibold text-ink-900">
                  {{ reservationStatusLabel(successReservation.reservation_status) }}
                  · {{ chargeStatusLabel(successReservation.charge_status) }}
                </dd>
              </div>
              <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Service fee</dt>
                <dd class="mt-1 font-semibold text-ink-900">
                  {{ formatReservationFee(successReservation.service_fee_amount, successReservation.service_fee_currency) }}
                </dd>
              </div>
            </dl>
            <p class="text-sm text-ink-600">{{ feeExplanation(successReservation.service_fee_amount) }}</p>
          </div>

          <div v-else class="px-5 py-5 space-y-4">
            <div class="flex gap-4">
              <div class="h-20 w-20 rounded-xl border border-ink-200 bg-ink-50 overflow-hidden shrink-0">
                <img
                  v-if="item?.image_url"
                  :src="item.image_url"
                  :alt="item.name"
                  class="h-full w-full object-cover"
                />
              </div>
              <div>
                <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ item?.category }}</p>
                <h4 class="text-base font-extrabold text-ink-900">{{ item?.name }}</h4>
                <p class="mt-1 text-sm text-ink-600">
                  {{ item?.vendor?.business_name || 'CMart Vendor' }}
                </p>
                <p v-if="item?.event" class="mt-1 text-sm text-emerald-700 font-medium">
                  {{ item.event.title }}
                  <span v-if="item.event.date_label"> · {{ item.event.date_label }}</span>
                </p>
              </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
              <p class="font-semibold">Reservation service fee</p>
              <p class="mt-1 font-bold" data-testid="reservation-fee-amount">
                {{ formatReservationFee(item?.reservation_service_fee, item?.reservation_service_fee_currency) }}
              </p>
              <p class="mt-2 leading-relaxed" data-testid="reservation-fee-explanation">
                {{ feeExplanation(item?.reservation_service_fee) }}
              </p>
              <p class="mt-2 text-xs text-amber-900/80">
                This is not an online purchase of the item and the platform does not process payment, delivery, or refunds.
              </p>
            </div>

            <p v-if="errorMessage" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
              {{ errorMessage }}
            </p>
          </div>

          <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-ink-100 px-5 py-4">
            <button
              type="button"
              class="ml-btn-ghost"
              data-testid="item-reservation-confirm-close"
              :disabled="submitting"
              @click="close"
            >
              {{ successReservation ? 'Close' : 'Cancel' }}
            </button>
            <router-link
              v-if="successReservation"
              :to="myReservationsHref"
              class="ml-btn-primary text-center"
              data-testid="item-reservation-go-to-mine"
              @click="close"
            >
              View My Reservations
            </router-link>
            <button
              v-else
              type="button"
              class="ml-btn-primary"
              data-testid="item-reservation-confirm-submit"
              :disabled="submitting"
              @click="submit"
            >
              {{ submitting ? 'Reserving…' : 'Confirm reservation' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useToast } from 'vue-toastification';
import { createItemReservation } from '../services/itemReservationsApi';
import {
  chargeStatusLabel,
  feeExplanation,
  formatReservationFee,
  myReservationsPath,
  reservationConflictCode,
  reservationErrorMessage,
  reservationStatusLabel,
} from '../utils/itemReservationDisplay';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'reserved', 'conflict']);

const toast = useToast();
const auth = useAuthStore();
const submitting = ref(false);
const errorMessage = ref('');
const successReservation = ref(null);

const myReservationsHref = computed(() => myReservationsPath(auth));

const close = () => {
  if (submitting.value) return;
  emit('update:modelValue', false);
};

const submit = async () => {
  if (!props.item?.id || submitting.value) return;
  submitting.value = true;
  errorMessage.value = '';
  try {
    const { data } = await createItemReservation(props.item.id);
    successReservation.value = data.reservation;
    toast.success('Reservation recorded successfully.');
    emit('reserved', data.reservation);
  } catch (error) {
    const code = reservationConflictCode(error);
    const message = reservationErrorMessage(error, 'Unable to reserve this item.');
    if (error?.response?.status === 409 && code === 'item_already_reserved') {
      toast.error(message);
      emit('conflict', { code, message });
      emit('update:modelValue', false);
      return;
    }
    errorMessage.value = message;
    toast.error(message);
  } finally {
    submitting.value = false;
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      errorMessage.value = '';
      successReservation.value = null;
      submitting.value = false;
    }
  },
);
</script>
