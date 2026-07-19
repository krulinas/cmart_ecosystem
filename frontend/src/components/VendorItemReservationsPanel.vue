<template>
  <section
    id="vendor-item-reservations"
    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5"
    data-testid="vendor-item-reservations-root"
  >
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h2 class="text-2xl font-extrabold text-ink-900">Item Reservations</h2>
        <p class="text-base text-ink-500 leading-relaxed">
          Track holds on your listed items. Mark collected when the item is handed over in person.
        </p>
      </div>
      <button class="ml-btn-ghost" :disabled="loading" data-testid="vendor-reservations-refresh" @click="load">
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && !rows.length" class="rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-500">
      Loading reservations…
    </div>
    <div
      v-else-if="loadError"
      class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-800"
      data-testid="vendor-reservations-error"
    >
      <p class="font-semibold">{{ loadError }}</p>
      <button type="button" class="mt-3 ml-btn-primary text-sm" @click="load">Try Again</button>
    </div>
    <div
      v-else-if="!rows.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
      data-testid="vendor-reservations-empty"
    >
      No reservations on your items yet.
    </div>
    <div v-else class="overflow-x-auto rounded-2xl border border-ink-100">
      <table class="min-w-full divide-y divide-ink-100 text-sm">
        <thead class="bg-ink-50/80">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Reference</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Item</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Reserver</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Fee</th>
            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 bg-white/70">
          <tr
            v-for="reservation in rows"
            :key="reservation.public_reference"
            data-testid="vendor-reservation-row"
            :data-public-reference="reservation.public_reference"
            :data-reservation-status="reservation.reservation_status"
            :data-charge-status="reservation.charge_status"
          >
            <td class="px-4 py-3 font-semibold text-ink-900">{{ reservation.public_reference }}</td>
            <td class="px-4 py-3 text-ink-700">{{ reservation.item?.name }}</td>
            <td class="px-4 py-3 text-ink-700" data-testid="vendor-reservation-reserver">
              {{ reservation.reserving_user?.name || '—' }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                :class="reservationStatusBadgeClass(reservation.reservation_status)"
              >
                {{ reservationStatusLabel(reservation.reservation_status) }}
              </span>
              <div class="mt-1 text-xs text-ink-500">
                {{ chargeStatusLabel(reservation.charge_status) }}
              </div>
            </td>
            <td class="px-4 py-3">
              {{ formatReservationFee(reservation.service_fee_amount, reservation.service_fee_currency) }}
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="detail = reservation">Details</button>
              <button
                v-if="canVendorCancel(reservation)"
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700"
                data-testid="vendor-reservation-cancel"
                @click="openCancel(reservation)"
              >
                Cancel
              </button>
              <button
                v-if="canCompleteReservation(reservation)"
                type="button"
                class="ml-btn-primary text-sm"
                data-testid="vendor-reservation-complete"
                @click="openComplete(reservation)"
              >
                Mark Collected
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div
        v-if="detail"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        data-testid="vendor-reservation-detail-modal"
        @keydown.esc="detail = null"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="detail = null" />
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
          <h3 class="text-lg font-extrabold text-ink-900">{{ detail.public_reference }}</h3>
          <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Item</dt><dd class="font-semibold">{{ detail.item?.name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Reserver</dt><dd>{{ detail.reserving_user?.name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Event</dt><dd>{{ detail.event?.title }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Status</dt><dd>{{ reservationStatusLabel(detail.reservation_status) }} · {{ chargeStatusLabel(detail.charge_status) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Fee</dt><dd>{{ formatReservationFee(detail.service_fee_amount, detail.service_fee_currency) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Created</dt><dd>{{ formatReservationTimestamp(detail.created_at) }}</dd></div>
          </dl>
          <p class="mt-4 text-xs text-ink-500">
            Reserver contact details are not shared here. Coordinate collection at the event booth.
          </p>
          <div class="mt-5 flex justify-end">
            <button type="button" class="ml-btn-ghost" @click="detail = null">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="cancelTarget"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        data-testid="vendor-reservation-cancel-modal"
        @keydown.esc="closeCancel"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="closeCancel" />
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
          <h3 class="text-lg font-extrabold text-ink-900">Cancel reservation?</h3>
          <p class="mt-2 text-sm text-ink-600">
            Cancelling clears the active hold. The platform does not issue refunds.
          </p>
          <label class="mt-4 block">
            <span class="ml-label">Reason {{ requiresAck ? '(required)' : '(optional)' }}</span>
            <textarea
              v-model="cancelReason"
              rows="3"
              class="ml-input"
              data-testid="vendor-cancel-reason"
              :disabled="mutating"
            />
          </label>
          <label
            v-if="requiresAck"
            class="mt-3 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/70 p-3 cursor-pointer"
            data-testid="vendor-no-refund-acknowledgement-label"
          >
            <input
              v-model="acknowledgeNoRefund"
              type="checkbox"
              class="mt-1 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500"
              data-testid="vendor-no-refund-acknowledgement"
              :disabled="mutating"
            />
            <span class="text-sm text-rose-900">
              I understand that any manually confirmed service fee will not be refunded by the platform.
            </span>
          </label>
          <p v-if="actionError" class="mt-3 text-sm text-rose-700">{{ actionError }}</p>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="mutating" @click="closeCancel">Keep</button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50"
              data-testid="vendor-reservation-cancel-confirm"
              :disabled="mutating || !canSubmitCancel"
              @click="confirmCancel"
            >
              {{ mutating ? 'Cancelling…' : 'Yes, cancel' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="completeTarget"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        data-testid="vendor-reservation-complete-modal"
        @keydown.esc="closeComplete"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="closeComplete" />
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
          <h3 class="text-lg font-extrabold text-ink-900">Mark item as collected?</h3>
          <p class="mt-2 text-sm text-ink-600">
            Confirm that the reserved item was handed over in person. The item will become inactive and the hold will clear.
          </p>
          <p v-if="actionError" class="mt-3 text-sm text-rose-700">{{ actionError }}</p>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="mutating" @click="closeComplete">Not yet</button>
            <button
              type="button"
              class="ml-btn-primary"
              data-testid="vendor-reservation-complete-confirm"
              :disabled="mutating"
              @click="confirmComplete"
            >
              {{ mutating ? 'Saving…' : 'Yes, mark collected' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import {
  cancelVendorItemReservation,
  completeVendorItemReservation,
  getVendorItemReservations,
} from '../services/itemReservationsApi';
import {
  canCompleteReservation,
  canVendorCancel,
  chargeStatusLabel,
  formatReservationFee,
  formatReservationTimestamp,
  requiresNoRefundAcknowledgement,
  reservationErrorMessage,
  reservationStatusBadgeClass,
  reservationStatusLabel,
} from '../utils/itemReservationDisplay';

const emit = defineEmits(['changed']);

const toast = useToast();
const rows = ref([]);
const loading = ref(false);
const loadError = ref('');
const detail = ref(null);
const cancelTarget = ref(null);
const completeTarget = ref(null);
const cancelReason = ref('');
const acknowledgeNoRefund = ref(false);
const actionError = ref('');
const mutating = ref(false);

const requiresAck = computed(() => requiresNoRefundAcknowledgement(cancelTarget.value));
const canSubmitCancel = computed(() => {
  if (!cancelTarget.value) return false;
  if (cancelTarget.value.reservation_status === 'confirmed' && !cancelReason.value.trim()) {
    return false;
  }
  if (requiresAck.value && !acknowledgeNoRefund.value) return false;
  return true;
});

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await getVendorItemReservations();
    rows.value = data.data || [];
  } catch (error) {
    loadError.value = reservationErrorMessage(error, 'Unable to load item reservations.');
  } finally {
    loading.value = false;
  }
};

const openCancel = (reservation) => {
  cancelTarget.value = reservation;
  cancelReason.value = '';
  acknowledgeNoRefund.value = false;
  actionError.value = '';
};

const closeCancel = () => {
  if (mutating.value) return;
  cancelTarget.value = null;
};

const confirmCancel = async () => {
  if (!cancelTarget.value || mutating.value || !canSubmitCancel.value) return;
  mutating.value = true;
  actionError.value = '';
  try {
    await cancelVendorItemReservation(cancelTarget.value.public_reference, {
      reason: cancelReason.value || null,
      acknowledge_no_refund: acknowledgeNoRefund.value,
    });
    toast.success('Reservation cancelled.');
    cancelTarget.value = null;
    await load();
    emit('changed');
  } catch (error) {
    actionError.value = reservationErrorMessage(error, 'Unable to cancel this reservation.');
    toast.error(actionError.value);
  } finally {
    mutating.value = false;
  }
};

const openComplete = (reservation) => {
  completeTarget.value = reservation;
  actionError.value = '';
};

const closeComplete = () => {
  if (mutating.value) return;
  completeTarget.value = null;
};

const confirmComplete = async () => {
  if (!completeTarget.value || mutating.value) return;
  mutating.value = true;
  actionError.value = '';
  try {
    await completeVendorItemReservation(completeTarget.value.public_reference);
    toast.success('Reservation marked completed. Item is now inactive.');
    completeTarget.value = null;
    await load();
    emit('changed');
  } catch (error) {
    actionError.value = reservationErrorMessage(error, 'Unable to complete this reservation.');
    toast.error(actionError.value);
  } finally {
    mutating.value = false;
  }
};

onMounted(load);

defineExpose({ load });
</script>
