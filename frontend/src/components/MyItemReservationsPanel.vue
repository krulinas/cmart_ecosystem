<template>
  <section
    id="my-item-reservations"
    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5"
    data-testid="my-item-reservations-root"
  >
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h2 class="text-2xl font-extrabold text-ink-900">{{ t('reservation.my.title') }}</h2>
        <p class="text-base text-ink-500 leading-relaxed">
          {{ t('reservation.my.lead') }}
        </p>
      </div>
      <button class="ml-btn-ghost" :disabled="loading" data-testid="my-reservations-refresh" @click="load">
        {{ loading ? t('reservation.my.refreshing') : t('reservation.my.refresh') }}
      </button>
    </div>

    <div v-if="loading && !rows.length" class="rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-500">
      {{ t('reservation.my.loading') }}
    </div>
    <div
      v-else-if="loadError"
      class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-800"
      data-testid="my-reservations-error"
    >
      <p class="font-semibold">{{ loadError }}</p>
      <button type="button" class="mt-3 ml-btn-primary text-sm" @click="load">{{ t('reservation.my.tryAgain') }}</button>
    </div>
    <div
      v-else-if="!rows.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
      data-testid="my-reservations-empty"
    >
      {{ t('reservation.my.empty') }}
      <router-link to="/marketplace" class="mt-3 block text-brand-600 font-semibold hover:text-brand-700">
        {{ t('reservation.my.browsePreview') }}
      </router-link>
    </div>
    <div v-else class="overflow-x-auto rounded-2xl border border-ink-100">
      <table class="min-w-full divide-y divide-ink-100 text-sm">
        <thead class="bg-ink-50/80">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colReference') }}</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colItem') }}</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colEvent') }}</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colStatus') }}</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colFee') }}</th>
            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('reservation.my.colAction') }}</th>
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
            <td class="px-4 py-3 text-ink-600">{{ reservation.event?.title || t('common.none') }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                :class="reservationStatusBadgeClass(reservation.reservation_status)"
              >
                {{ reservationStatusLabel(reservation.reservation_status, t) }}
              </span>
              <div class="mt-1 text-xs text-ink-500">
                {{ chargeStatusLabel(reservation.charge_status, t) }}
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
                {{ t('reservation.my.details') }}
              </button>
              <button
                v-if="canCommunityCancel(reservation)"
                type="button"
                class="ml-2 inline-flex items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700"
                data-testid="my-reservation-cancel"
                @click="openCancel(reservation)"
              >
                {{ t('reservation.my.cancel') }}
              </button>
              <a
                v-if="reservationWhatsapp(reservation)"
                :href="reservationWhatsapp(reservation).url"
                target="_blank"
                rel="noopener noreferrer"
                class="ml-2 ml-btn-ghost text-sm"
                data-testid="my-reservation-whatsapp"
                :aria-label="t('marketplace.details.contactWhatsAppAria', { name: reservation.vendor?.business_name || t('marketplace.details.vendor') })"
              >
                {{ t('reservation.my.contactVendor') }}
              </a>
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
          <h3 class="text-lg font-extrabold text-ink-900">{{ t('reservation.my.reservationTitle', { ref: detail.public_reference }) }}</h3>
          <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.colItem') }}</dt><dd class="font-semibold">{{ detail.item?.name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('marketplace.details.vendor') }}</dt><dd>{{ detail.vendor?.business_name }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.colEvent') }}</dt><dd>{{ detail.event?.title }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.colStatus') }}</dt><dd>{{ reservationStatusLabel(detail.reservation_status, t) }} · {{ chargeStatusLabel(detail.charge_status, t) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.colFee') }}</dt><dd>{{ formatReservationFee(detail.service_fee_amount, detail.service_fee_currency) }}</dd></div>
            <div><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.created') }}</dt><dd>{{ formatReservationTimestamp(detail.created_at) }}</dd></div>
            <div v-if="detail.cancelled_at"><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.cancelled') }}</dt><dd>{{ formatReservationTimestamp(detail.cancelled_at) }}</dd></div>
            <div v-if="detail.completed_at"><dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.completed') }}</dt><dd>{{ formatReservationTimestamp(detail.completed_at) }}</dd></div>
            <div
              v-if="showCollectionLocation(detail)"
              class="min-w-0"
              data-testid="my-reservation-collection-location"
            >
              <dt class="text-xs uppercase text-ink-400 font-bold">{{ t('reservation.my.collectionLocation') }}</dt>
              <dd class="mt-1 min-w-0 break-words text-ink-700">
                <template v-if="collectionLocationAvailable(detail)">
                  <p class="font-semibold text-ink-900" data-testid="my-reservation-collection-sites">
                    {{ collectionSiteLabel(detail, t) }}
                  </p>
                  <p
                    v-if="showCollectionInstruction(detail)"
                    class="mt-1 text-ink-600"
                    data-testid="my-reservation-collection-instruction"
                  >
                    {{ t('reservation.my.collectionInstruction') }}
                  </p>
                </template>
                <template v-else>
                  <p class="font-semibold text-ink-900" data-testid="my-reservation-collection-unassigned">
                    {{ t('reservation.my.siteUnassigned') }}
                  </p>
                  <p
                    v-if="showCollectionInstruction(detail)"
                    class="mt-1 text-ink-600"
                    data-testid="my-reservation-collection-unassigned-hint"
                  >
                    {{ t('reservation.my.siteUnassignedHint') }}
                  </p>
                </template>
              </dd>
            </div>
          </dl>
          <div class="mt-5 flex flex-wrap justify-end gap-2">
            <button type="button" class="ml-btn-ghost" @click="detail = null">{{ t('reservation.my.close') }}</button>
            <a
              v-if="reservationWhatsapp(detail)"
              :href="reservationWhatsapp(detail).url"
              target="_blank"
              rel="noopener noreferrer"
              class="ml-btn-ghost"
              data-testid="my-reservation-detail-whatsapp"
              :aria-label="t('marketplace.details.contactWhatsAppAria', { name: detail.vendor?.business_name || t('marketplace.details.vendor') })"
            >
              {{ t('reservation.my.contactVendor') }}
            </a>
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
          <h3 class="text-lg font-extrabold text-ink-900">{{ t('reservation.my.cancelTitle') }}</h3>
          <p class="mt-2 text-sm text-ink-600">
            {{ t('reservation.my.cancelBody') }}
          </p>
          <label class="mt-4 block">
            <span class="ml-label">{{ t('reservation.my.reasonOptional') }}</span>
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
            <button type="button" class="ml-btn-ghost" :disabled="cancelling" @click="closeCancel">{{ t('reservation.my.keep') }}</button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50"
              data-testid="my-reservation-cancel-confirm"
              :disabled="cancelling"
              @click="confirmCancel"
            >
              {{ cancelling ? t('reservation.my.cancelling') : t('reservation.my.yesCancel') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { cancelMyItemReservation, getMyItemReservations } from '../services/itemReservationsApi';
import {
  canCommunityCancel,
  chargeStatusLabel,
  collectionLocationAvailable,
  collectionSiteLabel,
  formatReservationFee,
  formatReservationTimestamp,
  reservationErrorMessage,
  reservationStatusBadgeClass,
  reservationStatusLabel,
  showCollectionInstruction,
  showCollectionLocation,
} from '../utils/itemReservationDisplay';
import { vendorWhatsappContact } from '../utils/whatsappContact';

const { t } = useI18n();
const toast = useToast();
const reservationWhatsapp = (reservation) => vendorWhatsappContact(reservation);
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
    loadError.value = reservationErrorMessage(error, t('reservation.my.loadError'));
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
    toast.success(t('reservation.my.toastCancelled'));
    cancelTarget.value = null;
    await load();
  } catch (error) {
    cancelError.value = reservationErrorMessage(error, t('reservation.my.cancelError'));
    toast.error(cancelError.value);
  } finally {
    cancelling.value = false;
  }
};

onMounted(load);

defineExpose({ load });
</script>
