<template>
  <section
    id="my-item-reservations"
    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5"
    data-testid="my-item-reservations-root"
  >
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h2 class="text-2xl font-extrabold text-ink-900">My Reservations</h2>
        <p class="text-base text-ink-500 leading-relaxed">
          Track holds you placed on marketplace items. Collect reserved items in person at the event.
        </p>
      </div>
      <button class="ml-btn-ghost" :disabled="loading" data-testid="my-reservations-refresh" @click="load">
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && !rows.length" class="rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-500">
      Loading reservations…
    </div>
    <div
      v-else-if="loadError"
      class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-800"
      data-testid="my-reservations-error"
    >
      <p class="font-semibold">{{ loadError }}</p>
      <button type="button" class="mt-3 ml-btn-primary text-sm" @click="load">Try Again</button>
    </div>
    <div
      v-else-if="!rows.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
      data-testid="my-reservations-empty"
    >
      You have not reserved any items yet.
      <router-link to="/marketplace" class="mt-3 block text-brand-600 font-semibold hover:text-brand-700">
        Browse Carboot Preview →
      </router-link>
    </div>
    <div v-else class="overflow-x-auto rounded-2xl border border-ink-100">
      <table class="min-w-full divide-y divide-ink-100 text-sm">
        <thead class="bg-ink-50/80">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Reference</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Item</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Event</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Fee</th>
            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 bg-white/70">
          <tr
            v-for="reservation in rows"
            :key="reservation.public_reference"
            data-testid="my-reservation-row"
            :data-public-reference="reservation.public_reference"
            :data-reservation-status="reservation.reservation_status"
            :data-charge-status="reservation.charge_status"
          >
            <td class="px-4 py-3 font-semibold text-ink-900">{{ reservation.public_reference }}</td>
            <td class="px-4 py-3 text-ink-700">
              <div class="font-medium">{{ reservation.item?.name }}</div>
              <div class="text-xs text-ink-500">{{ reservation.vendor?.business_name }}</div>
            </td>
            <td class="px-4 py-3 text-ink-600">{{ reservation.event?.title || '—' }}</td>
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
            <td class="px-4 py-3 text-ink-700">
              {{ formatReservationFee(reservation.service_fee_amount, reservation.service_fee_currency) }}
            </td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="ml-btn-ghost text-sm"
                data-testid="my-reservation-view"
                @click="openDetail(reservation)"
              >
                Details
              </button>
              <button
                v-if="canCommunityCancel(reservation)"
                type="button"
                class="ml-2 inline-flex items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700"
                data-testid="my-reservation-cancel"
                @click="openCancel(reservation)"
              >
                Cancel
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
        data-testid="my-reservation-detail-modal"
        @keydown.esc="detail = null"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="detail = null" />
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
          <h3 class="text-lg font-extrabold text-ink-900">Reservation {{ detail.public_reference }}</h3>
          <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Item</dt><dd class="font-semibold">{{ detail.item?.name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Vendor</dt><dd>{{ detail.vendor?.business_name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Event</dt><dd>{{ detail.event?.title }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Status</dt><dd>{{ reservationStatusLabel(detail.reservation_status) }} · {{ chargeStatusLabel(detail.charge_status) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Fee</dt><dd>{{ formatReservationFee(detail.service_fee_amount, detail.service_fee_currency) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">Created</dt><dd>{{ formatReservationTimestamp(detail.created_at) }}</dd></div>
            <div v-if="detail.cancelled_at"><dt class="text-xs uppercase text-ink-400 font-bold">Cancelled</dt><dd>{{ formatReservationTimestamp(detail.cancelled_at) }}</dd></div>
            <div v-if="detail.completed_at"><dt class="text-xs uppercase text-ink-400 font-bold">Completed</dt><dd>{{ formatReservationTimestamp(detail.completed_at) }}</dd></div>
          </dl>
          <div class="mt-5 flex justify-end gap-2">
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
        data-testid="my-reservation-cancel-modal"
        @keydown.esc="closeCancel"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="closeCancel" />
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
          <h3 class="text-lg font-extrabold text-ink-900">Cancel reservation?</h3>
          <p class="mt-2 text-sm text-ink-600">
            Only pending-charge reservations can be cancelled. Confirmed holds remain until the vendor or Organizer acts.
          </p>
          <label class="mt-4 block">
            <span class="ml-label">Reason (optional)</span>
            <textarea
              v-model="cancelReason"
              rows="3"
              class="ml-input"
              data-testid="my-reservation-cancel-reason"
              :disabled="cancelling"
            />
          </label>
          <p
            v-if="cancelError"
            class="mt-3 text-sm text-rose-700"
            data-testid="my-reservation-cancel-error"
          >
            {{ cancelError }}
          </p>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="cancelling" @click="closeCancel">Keep</button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50"
              data-testid="my-reservation-cancel-confirm"
              :disabled="cancelling"
              @click="confirmCancel"
            >
              {{ cancelling ? 'Cancelling…' : 'Yes, cancel' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { cancelMyItemReservation, getMyItemReservations } from '../services/itemReservationsApi';
import {
  canCommunityCancel,
  chargeStatusLabel,
  formatReservationFee,
  formatReservationTimestamp,
  reservationErrorMessage,
  reservationStatusBadgeClass,
  reservationStatusLabel,
} from '../utils/itemReservationDisplay';

const toast = useToast();
const rows = ref([]);
const loading = ref(false);
const loadError = ref('');
const detail = ref(null);
const cancelTarget = ref(null);
const cancelReason = ref('');
const cancelError = ref('');
const cancelling = ref(false);

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await getMyItemReservations();
    rows.value = data.data || [];
  } catch (error) {
    loadError.value = reservationErrorMessage(error, 'Unable to load your reservations.');
  } finally {
    loading.value = false;
  }
};

const openDetail = (reservation) => {
  detail.value = reservation;
};

const openCancel = (reservation) => {
  cancelTarget.value = reservation;
  cancelReason.value = '';
  cancelError.value = '';
};

const closeCancel = () => {
  if (cancelling.value) return;
  cancelTarget.value = null;
};

const confirmCancel = async () => {
  if (!cancelTarget.value || cancelling.value) return;
  cancelling.value = true;
  cancelError.value = '';
  try {
    await cancelMyItemReservation(cancelTarget.value.public_reference, {
      reason: cancelReason.value || null,
    });
    toast.success('Reservation cancelled.');
    cancelTarget.value = null;
    await load();
  } catch (error) {
    cancelError.value = reservationErrorMessage(error, 'Unable to cancel this reservation.');
    toast.error(cancelError.value);
  } finally {
    cancelling.value = false;
  }
};

onMounted(load);

defineExpose({ load });
</script>
