<template>
  <div class="space-y-5" data-testid="organizer-item-reservations-panel">
    <section class="ml-card space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Item Reservations</p>
          <h2 class="text-xl font-extrabold text-ink-900">Event reservation queue</h2>
          <p class="mt-1 text-sm text-ink-500">
            Reconcile manual off-platform service fees. The platform records Organizer confirmation only and never processes payment.
          </p>
        </div>
        <button
          type="button"
          class="ml-btn-ghost text-sm"
          :disabled="!selectedEventId || loading"
          data-testid="organizer-reservations-refresh"
          @click="loadQueue"
        >
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>

      <div class="grid gap-3 md:grid-cols-4 md:items-end">
        <div class="md:col-span-2">
          <label class="ml-label" for="organizer-reservation-event">Event</label>
          <select
            id="organizer-reservation-event"
            v-model="selectedEventId"
            class="ml-input"
            data-testid="organizer-reservation-event-select"
            :disabled="loadingEvents"
            @change="onEventSelected"
          >
            <option value="">— Select event —</option>
            <option v-for="event in events" :key="event.id" :value="String(event.id)">
              {{ event.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="ml-label" for="organizer-reservation-status">Reservation status</label>
          <select
            id="organizer-reservation-status"
            v-model="reservationStatus"
            class="ml-input"
            data-testid="organizer-reservation-status-filter"
            @change="loadQueue"
          >
            <option value="">All</option>
            <option v-for="(label, value) in RESERVATION_STATUS_LABELS" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </div>
        <div>
          <label class="ml-label" for="organizer-charge-status">Charge status</label>
          <select
            id="organizer-charge-status"
            v-model="chargeStatus"
            class="ml-input"
            data-testid="organizer-charge-status-filter"
            @change="loadQueue"
          >
            <option value="">All</option>
            <option v-for="(label, value) in CHARGE_STATUS_LABELS" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </div>
      </div>
    </section>

    <div v-if="!selectedEventId" class="ml-card text-sm text-ink-500" data-testid="organizer-reservations-empty-event">
      Select an event to load its reservation queue.
    </div>
    <div v-else-if="loading && !rows.length" class="ml-card animate-pulse py-10 text-center text-ink-500">
      Loading reservations…
    </div>
    <div v-else-if="loadError" class="ml-card border-rose-200 bg-rose-50 space-y-3" data-testid="organizer-reservations-error">
      <p class="font-semibold text-rose-900">{{ loadError }}</p>
      <button type="button" class="ml-btn-primary text-sm" @click="loadQueue">Try Again</button>
    </div>
    <div v-else-if="!rows.length" class="ml-card text-sm text-ink-500" data-testid="organizer-reservations-empty">
      No reservations match the current filters.
    </div>
    <section v-else class="ml-card overflow-x-auto" data-testid="organizer-reservations-queue">
      <table class="min-w-full divide-y divide-ink-100 text-sm">
        <thead class="bg-ink-50/80">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Reference</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Item</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Vendor</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Reserver</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">Fee</th>
            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 bg-white">
          <tr
            v-for="row in rows"
            :key="row.public_reference"
            data-testid="organizer-reservation-row"
            :data-public-reference="row.public_reference"
            :data-reservation-status="row.reservation_status"
            :data-charge-status="row.charge_status"
          >
            <td class="px-4 py-3 font-semibold text-ink-900">{{ row.public_reference }}</td>
            <td class="px-4 py-3 text-ink-700">{{ row.item_name }}</td>
            <td class="px-4 py-3 text-ink-700">
              <div>{{ row.vendor?.business_name || row.vendor?.name }}</div>
              <div class="text-xs text-ink-500">{{ row.vendor?.email }}</div>
            </td>
            <td class="px-4 py-3 text-ink-700">
              <div>{{ row.reserving_user?.name }}</div>
              <div class="text-xs text-ink-500">{{ row.reserving_user?.email }}</div>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                :class="reservationStatusBadgeClass(row.reservation_status)"
              >
                {{ reservationStatusLabel(row.reservation_status) }}
              </span>
              <div class="mt-1 text-xs text-ink-500">{{ chargeStatusLabel(row.charge_status) }}</div>
              <div class="mt-1 text-[11px] text-ink-400">{{ formatReservationTimestamp(row.created_at) }}</div>
            </td>
            <td class="px-4 py-3">
              {{ formatReservationFee(row.service_fee_amount, row.service_fee_currency) }}
            </td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="ml-btn-ghost text-sm"
                data-testid="organizer-reservation-open"
                @click="openDetail(row.public_reference)"
              >
                Details
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
        <p class="text-ink-500">Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</p>
        <div class="flex gap-2">
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="meta.current_page <= 1 || loading"
            @click="goToPage(meta.current_page - 1)"
          >
            Previous
          </button>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="goToPage(meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="detailOpen"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        data-testid="organizer-reservation-detail-modal"
        @keydown.esc="closeDetail"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="closeDetail" />
        <div class="relative z-10 w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl" @click.stop>
          <div class="border-b border-ink-100 px-5 py-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-extrabold text-ink-900">
                {{ detail?.public_reference || 'Reservation detail' }}
              </h3>
              <p class="text-sm text-ink-500 mt-1">
                Manual off-platform fee reconciliation — not a payment gateway receipt.
              </p>
            </div>
            <button type="button" class="ml-btn-ghost text-sm" :disabled="mutating" @click="closeDetail">Close</button>
          </div>

          <div v-if="detailLoading" class="p-8 text-center text-ink-500">Loading detail…</div>
          <div v-else-if="detailError" class="p-6 text-rose-700">{{ detailError }}</div>
          <div v-else-if="detail" class="p-5 space-y-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Item</dt>
                <dd class="font-semibold text-ink-900">{{ detail.item?.name }}</dd>
              </div>
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Event</dt>
                <dd class="font-semibold text-ink-900">{{ detail.event?.title }}</dd>
              </div>
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Vendor</dt>
                <dd>{{ detail.vendor?.business_name || detail.vendor?.name }}</dd>
                <dd class="text-xs text-ink-500">{{ detail.vendor?.email }}</dd>
              </div>
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Reserving user</dt>
                <dd>{{ detail.reserving_user?.name }}</dd>
                <dd class="text-xs text-ink-500">{{ detail.reserving_user?.email }}</dd>
              </div>
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Statuses</dt>
                <dd>
                  {{ reservationStatusLabel(detail.reservation_status) }}
                  · {{ chargeStatusLabel(detail.charge_status) }}
                </dd>
              </div>
              <div class="rounded-xl border border-ink-100 p-3">
                <dt class="text-xs font-bold uppercase text-ink-400">Service fee</dt>
                <dd>{{ formatReservationFee(detail.service_fee_amount, detail.service_fee_currency) }}</dd>
              </div>
            </dl>

            <div class="rounded-xl border border-ink-100 p-4 text-sm space-y-2">
              <h4 class="font-bold text-ink-900">Charge evidence</h4>
              <p>Confirmation note: {{ detail.charge_confirmation?.note || '—' }}</p>
              <p>Confirmed by: {{ detail.charge_confirmation?.confirmed_by || '—' }} · {{ formatReservationTimestamp(detail.charge_confirmation?.confirmed_at) }}</p>
              <p>Waiver reason: {{ detail.charge_waiver?.reason || '—' }}</p>
              <p>Waived by: {{ detail.charge_waiver?.waived_by || '—' }} · {{ formatReservationTimestamp(detail.charge_waiver?.waived_at) }}</p>
              <p>Cancelled by: {{ detail.cancelled_by || '—' }} · {{ formatReservationTimestamp(detail.cancelled_at) }}</p>
              <p>Expired by: {{ detail.expired_by || '—' }} · {{ formatReservationTimestamp(detail.expired_at) }}</p>
              <p>Completed by: {{ detail.completed_by || '—' }} · {{ formatReservationTimestamp(detail.completed_at) }}</p>
            </div>

            <div class="flex flex-wrap gap-2" data-testid="organizer-reservation-actions">
              <button
                v-if="canOrganizerConfirmCharge(detail)"
                type="button"
                class="ml-btn-primary text-sm"
                data-testid="organizer-confirm-charge-open"
                :disabled="mutating"
                @click="actionMode = 'confirm'"
              >
                Confirm charge
              </button>
              <button
                v-if="canOrganizerWaiveCharge(detail)"
                type="button"
                class="ml-btn-ghost text-sm"
                data-testid="organizer-waive-charge-open"
                :disabled="mutating"
                @click="actionMode = 'waive'"
              >
                Waive charge
              </button>
              <button
                v-if="canOrganizerCancelOrExpire(detail)"
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50"
                data-testid="organizer-cancel-open"
                :disabled="mutating"
                @click="actionMode = 'cancel'"
              >
                Cancel
              </button>
              <button
                v-if="canOrganizerCancelOrExpire(detail)"
                type="button"
                class="ml-btn-ghost text-sm"
                data-testid="organizer-expire-open"
                :disabled="mutating"
                @click="actionMode = 'expire'"
              >
                Manual expiry
              </button>
              <button
                v-if="canCompleteReservation(detail)"
                type="button"
                class="ml-btn-primary text-sm"
                data-testid="organizer-complete-open"
                :disabled="mutating"
                @click="actionMode = 'complete'"
              >
                Mark collected
              </button>
            </div>

            <div
              v-if="actionMode"
              class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3"
              data-testid="organizer-reservation-action-form"
              :data-action-mode="actionMode"
            >
              <h4 class="font-bold text-ink-900">{{ actionTitle }}</h4>
              <p class="text-sm text-ink-600">{{ actionHelp }}</p>
              <label v-if="needsNote" class="block">
                <span class="ml-label">{{ noteLabel }}</span>
                <textarea
                  v-model="actionNote"
                  rows="3"
                  class="ml-input"
                  data-testid="organizer-action-note"
                  maxlength="500"
                  :disabled="mutating"
                />
              </label>
              <label
                v-if="actionMode === 'cancel' && requiresNoRefundAcknowledgement(detail)"
                class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/70 p-3 cursor-pointer"
              >
                <input
                  v-model="acknowledgeNoRefund"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500"
                  data-testid="organizer-no-refund-acknowledgement"
                  :disabled="mutating"
                />
                <span class="text-sm text-rose-900">
                  I acknowledge that the manually confirmed service fee will not be refunded by the platform.
                </span>
              </label>
              <p
                v-if="actionError"
                class="text-sm text-rose-700"
                data-testid="organizer-action-error"
              >
                {{ actionError }}
              </p>
              <div class="flex justify-end gap-2">
                <button type="button" class="ml-btn-ghost text-sm" :disabled="mutating" @click="resetAction">Back</button>
                <button
                  type="button"
                  class="ml-btn-primary text-sm"
                  data-testid="organizer-action-submit"
                  :disabled="mutating || !canSubmitAction"
                  @click="submitAction"
                >
                  {{ mutating ? 'Saving…' : 'Submit' }}
                </button>
              </div>
            </div>

            <div data-testid="organizer-reservation-audit-timeline">
              <h4 class="font-bold text-ink-900 mb-3">Audit timeline</h4>
              <div v-if="!audits.length" class="text-sm text-ink-500">No audit entries yet.</div>
              <ol v-else class="space-y-3">
                <li
                  v-for="(audit, index) in audits"
                  :key="`${audit.action}-${audit.created_at}-${index}`"
                  class="rounded-xl border border-ink-100 bg-ink-50/50 p-3 text-sm"
                  data-testid="organizer-reservation-audit-item"
                  :data-audit-action="audit.action"
                >
                  <p class="font-semibold text-ink-900">{{ auditActionLabel(audit.action) }}</p>
                  <p class="text-ink-600 mt-1">
                    {{ audit.actor || 'System' }} · {{ formatReservationTimestamp(audit.created_at) }}
                  </p>
                  <p class="text-ink-600 mt-1">
                    {{ reservationStatusLabel(audit.from_reservation_status) }}
                    → {{ reservationStatusLabel(audit.to_reservation_status) }}
                    · {{ chargeStatusLabel(audit.from_charge_status) }}
                    → {{ chargeStatusLabel(audit.to_charge_status) }}
                  </p>
                  <p v-if="audit.note" class="mt-1 text-ink-700 whitespace-pre-line">{{ audit.note }}</p>
                </li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { getCarbootEvents } from '../../../services/organizerEventLayoutApi';
import {
  cancelOrganizerItemReservation,
  completeOrganizerItemReservation,
  confirmOrganizerItemReservationCharge,
  expireOrganizerItemReservation,
  getOrganizerEventItemReservations,
  getOrganizerItemReservation,
  getOrganizerItemReservationAudits,
  waiveOrganizerItemReservationCharge,
} from '../../../services/itemReservationsApi';
import {
  CHARGE_STATUS_LABELS,
  RESERVATION_STATUS_LABELS,
  auditActionLabel,
  canCompleteReservation,
  canOrganizerCancelOrExpire,
  canOrganizerConfirmCharge,
  canOrganizerWaiveCharge,
  chargeStatusLabel,
  formatReservationFee,
  formatReservationTimestamp,
  requiresNoRefundAcknowledgement,
  reservationErrorMessage,
  reservationStatusBadgeClass,
  reservationStatusLabel,
} from '../../../utils/itemReservationDisplay';

const toast = useToast();
const route = useRoute();
const router = useRouter();

const events = ref([]);
const loadingEvents = ref(false);
const selectedEventId = ref('');
const reservationStatus = ref('');
const chargeStatus = ref('');
const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const loading = ref(false);
const loadError = ref('');

const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detail = ref(null);
const audits = ref([]);
const actionMode = ref('');
const actionNote = ref('');
const acknowledgeNoRefund = ref(false);
const actionError = ref('');
const mutating = ref(false);

const actionTitle = computed(() => ({
  confirm: 'Record manual charge confirmation',
  waive: 'Waive service fee',
  cancel: 'Cancel active reservation',
  expire: 'Manually expire reservation',
  complete: 'Mark item collected',
}[actionMode.value] || ''));

const actionHelp = computed(() => ({
  confirm: 'Record that the Organizer received the service fee off-platform. This does not process payment.',
  waive: 'Waive the required service fee and confirm the reservation. Not the same as a zero-fee not_required charge.',
  cancel: 'Clear the active hold. Confirmed charge history remains; the platform issues no refund.',
  expire: 'Manually expire this active reservation. This is not an automatic timeout.',
  complete: 'Confirm the reserved item was handed over. The item becomes inactive.',
}[actionMode.value] || ''));

const needsNote = computed(() => ['confirm', 'waive', 'cancel', 'expire'].includes(actionMode.value));
const noteLabel = computed(() => (actionMode.value === 'confirm' ? 'Confirmation note' : 'Reason'));

const canSubmitAction = computed(() => {
  if (!actionMode.value || mutating.value) return false;
  if (needsNote.value && !actionNote.value.trim()) return false;
  if (
    actionMode.value === 'cancel'
    && requiresNoRefundAcknowledgement(detail.value)
    && !acknowledgeNoRefund.value
  ) {
    return false;
  }
  return true;
});

const loadEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await getCarbootEvents();
    events.value = data.events || data.data || data || [];
  } catch (error) {
    if (!error.forbiddenMessage) {
      toast.error(reservationErrorMessage(error, 'Unable to load events.'));
    }
  } finally {
    loadingEvents.value = false;
  }
};

const syncEventQuery = () => {
  const nextQuery = { ...route.query };
  if (selectedEventId.value) nextQuery.eventId = selectedEventId.value;
  else delete nextQuery.eventId;
  router.replace({ hash: '#item-reservations', query: nextQuery });
};

const onEventSelected = async () => {
  meta.value.current_page = 1;
  syncEventQuery();
  await loadQueue();
};

const loadQueue = async () => {
  if (!selectedEventId.value) {
    rows.value = [];
    return;
  }
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await getOrganizerEventItemReservations(selectedEventId.value, {
      reservation_status: reservationStatus.value || undefined,
      charge_status: chargeStatus.value || undefined,
      page: meta.value.current_page,
      per_page: 20,
    });
    rows.value = data.data || [];
    meta.value = data.meta || meta.value;
  } catch (error) {
    loadError.value = reservationErrorMessage(error, 'Unable to load the reservation queue.');
  } finally {
    loading.value = false;
  }
};

const goToPage = async (page) => {
  meta.value.current_page = page;
  await loadQueue();
};

const resetAction = () => {
  actionMode.value = '';
  actionNote.value = '';
  acknowledgeNoRefund.value = false;
  actionError.value = '';
};

const openDetail = async (publicReference) => {
  detailOpen.value = true;
  detailLoading.value = true;
  detailError.value = '';
  detail.value = null;
  audits.value = [];
  resetAction();
  try {
    const [detailResponse, auditsResponse] = await Promise.all([
      getOrganizerItemReservation(publicReference),
      getOrganizerItemReservationAudits(publicReference),
    ]);
    detail.value = detailResponse.data.reservation;
    audits.value = auditsResponse.data.audits || [];
  } catch (error) {
    detailError.value = reservationErrorMessage(error, 'Unable to load reservation detail.');
  } finally {
    detailLoading.value = false;
  }
};

const closeDetail = () => {
  if (mutating.value) return;
  detailOpen.value = false;
  detail.value = null;
  audits.value = [];
  resetAction();
};

const refreshDetailAndQueue = async () => {
  if (detail.value?.public_reference) {
    await openDetail(detail.value.public_reference);
  }
  await loadQueue();
};

const submitAction = async () => {
  if (!detail.value || !canSubmitAction.value || mutating.value) return;
  mutating.value = true;
  actionError.value = '';
  const reference = detail.value.public_reference;
  try {
    if (actionMode.value === 'confirm') {
      await confirmOrganizerItemReservationCharge(reference, actionNote.value.trim());
    } else if (actionMode.value === 'waive') {
      await waiveOrganizerItemReservationCharge(reference, actionNote.value.trim());
    } else if (actionMode.value === 'cancel') {
      await cancelOrganizerItemReservation(reference, {
        reason: actionNote.value.trim(),
        acknowledge_no_refund: acknowledgeNoRefund.value,
      });
    } else if (actionMode.value === 'expire') {
      await expireOrganizerItemReservation(reference, actionNote.value.trim());
    } else if (actionMode.value === 'complete') {
      await completeOrganizerItemReservation(reference);
    }
    toast.success('Reservation updated.');
    resetAction();
    await refreshDetailAndQueue();
  } catch (error) {
    actionError.value = reservationErrorMessage(error, 'Unable to update this reservation.');
    toast.error(actionError.value);
    if (error?.response?.status === 409) {
      await refreshDetailAndQueue();
    }
  } finally {
    mutating.value = false;
  }
};

const load = async () => {
  await loadEvents();
  const fromQuery = route.query.eventId ? String(route.query.eventId) : '';
  if (fromQuery) {
    selectedEventId.value = fromQuery;
    await loadQueue();
  }
};

watch(
  () => route.query.eventId,
  async (eventId) => {
    const next = eventId ? String(eventId) : '';
    if (next !== selectedEventId.value) {
      selectedEventId.value = next;
      if (next) await loadQueue();
    }
  },
);

onMounted(load);

defineExpose({ load });
</script>
