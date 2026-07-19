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
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        data-testid="public-detail-modal"
        aria-labelledby="marketplace-item-details-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden max-h-[90vh] overflow-y-auto" @click.stop>
          <div v-if="loading" class="p-10 text-center text-ink-500">Loading item details…</div>

          <div v-else-if="loadError" class="p-10 text-center">
            <p class="text-sm text-rose-700 font-semibold">Unable to load this preview item.</p>
            <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="loadItem">Try Again</button>
          </div>

          <template v-else-if="item">
            <ReuseItemImageGallery :item="item" :alt-text="item.name" />

            <div class="p-6 sm:p-8">
              <div
                class="mb-5 rounded-xl border px-4 py-3 text-sm leading-relaxed"
                :class="item.is_reservable
                  ? 'border-emerald-300 bg-emerald-50 text-emerald-950'
                  : 'border-amber-400 bg-[#FFFBEB] text-[#92400E]'"
                role="note"
              >
                <p
                  class="font-semibold"
                  :class="item.is_reservable ? 'text-emerald-900' : 'text-[#78350F]'"
                >
                  {{ item.is_reservable
                    ? 'Reserve a hold, then collect in person at the event.'
                    : 'Preview only: in-person purchase at the event.' }}
                </p>
                <p class="mt-1">
                  {{ item.is_reservable
                    ? 'Reserving records a hold and any Organizer service fee. The item itself is still collected and paid for in person at the vendor booth.'
                    : 'This item is shown to help you plan your visit. Please go to the vendor booth during the CMart Carboot event to view, confirm availability, and purchase in person.' }}
                </p>
              </div>

              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ item.category }}</p>
                  <h2 id="marketplace-item-details-title" class="mt-1 text-2xl font-extrabold text-ink-900">{{ item.name }}</h2>
                  <p class="mt-2 text-sm text-emerald-700 font-medium">
                    Available at CMart Carboot
                    <span v-if="item.event?.date_label"> · {{ item.event.date_label }}</span>
                    . Purchase: In-person only.
                  </p>
                  <p
                    v-if="item.has_active_reservation"
                    class="mt-2 text-sm font-semibold text-amber-700"
                    data-testid="marketplace-item-already-reserved"
                  >
                    This item already has an active reservation.
                  </p>
                </div>
                <p class="text-lg font-black text-brand-700 shrink-0">
                  Guide Price: {{ formatItemPrice(item) }}
                </p>
              </div>

              <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Condition</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ item.condition }}</dd>
                </div>
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Budget Guide</dt>
                  <dd class="mt-1 font-semibold text-ink-900 capitalize">{{ item.pricing_type.replace('_', ' ') }}</dd>
                </div>
                <div
                  v-if="item.reservation_service_fee != null"
                  class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 sm:col-span-2"
                  data-testid="marketplace-reservation-fee"
                >
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Reservation service fee</dt>
                  <dd class="mt-1 font-semibold text-ink-900">
                    {{ formatReservationFee(item.reservation_service_fee, item.reservation_service_fee_currency) }}
                  </dd>
                </div>
              </dl>

              <div v-if="item.description" class="mt-4 rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-ink-400">Description</h3>
                <p class="mt-2 text-sm text-ink-700 whitespace-pre-line">{{ item.description }}</p>
              </div>

              <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50/40 p-5">
                <div class="flex items-start gap-4">
                  <div class="h-14 w-14 rounded-xl border border-ink-200 bg-white overflow-hidden flex items-center justify-center shrink-0">
                    <img
                      v-if="item.vendor?.logo_url"
                      :src="item.vendor.logo_url"
                      :alt="`${item.vendor.business_name} logo`"
                      class="h-full w-full object-cover"
                    />
                    <span v-else class="text-[10px] font-bold uppercase text-ink-400">Vendor</span>
                  </div>
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-400">Vendor</p>
                    <h3 class="text-lg font-bold text-ink-900">{{ item.vendor?.business_name || 'CMart Vendor' }}</h3>
                    <p v-if="item.vendor?.business_category" class="text-sm text-brand-700 font-semibold mt-0.5">
                      {{ item.vendor.business_category }}
                    </p>
                    <p v-if="item.vendor?.description" class="mt-2 text-sm text-ink-600 line-clamp-4">
                      {{ item.vendor.description }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" class="ml-btn-ghost" @click="close">Close</button>
                <router-link
                  v-if="reserveMode === 'login'"
                  :to="loginHref"
                  class="ml-btn-primary"
                  data-testid="marketplace-reserve-login"
                >
                  Log in to Reserve
                </router-link>
                <button
                  v-else-if="reserveMode === 'reserve'"
                  type="button"
                  class="ml-btn-primary"
                  data-testid="marketplace-reserve-cta"
                  @click="showReserveModal = true"
                >
                  Reserve
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>

  <ItemReservationConfirmModal
    v-model="showReserveModal"
    :item="item"
    @reserved="onReserved"
    @conflict="onReserveConflict"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../services/api';
import ReuseItemImageGallery from './ReuseItemImageGallery.vue';
import ItemReservationConfirmModal from './ItemReservationConfirmModal.vue';
import { normalizeReuseItem } from '../utils/imageUrl';
import { formatItemPrice } from '../utils/vendorCatalog';
import { formatReservationFee, reserveCtaMode } from '../utils/itemReservationDisplay';
import { loginPathWithRedirect } from '../utils/postAuthRedirect';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'reserved']);

const auth = useAuthStore();
const item = ref(null);
const loading = ref(false);
const loadError = ref(false);
const showReserveModal = ref(false);

const close = () => emit('update:modelValue', false);

const reserveMode = computed(() => reserveCtaMode({
  item: item.value,
  isAuthenticated: auth.isAuthenticated,
  isCommunityMember: auth.isCommunityMember,
  isCmartWorker: auth.isCmartWorker,
}));

const loginHref = computed(() => {
  const redirect = props.itemId
    ? `/marketplace?item=${encodeURIComponent(props.itemId)}`
    : '/marketplace';
  return loginPathWithRedirect(redirect);
});

const loadItem = async () => {
  if (!props.itemId) return;
  loading.value = true;
  loadError.value = false;
  try {
    const { data } = await api.get(`/marketplace/items/${props.itemId}`);
    item.value = normalizeReuseItem(data.item);
  } catch (error) {
    console.error('Unable to load preview item:', error);
    loadError.value = true;
    item.value = null;
  } finally {
    loading.value = false;
  }
};

const onReserved = (reservation) => {
  emit('reserved', reservation);
  loadItem();
};

const onReserveConflict = async () => {
  showReserveModal.value = false;
  await loadItem();
};

watch(
  () => [props.modelValue, props.itemId],
  ([open, id]) => {
    if (open && id) loadItem();
    if (!open) {
      item.value = null;
      showReserveModal.value = false;
    }
  },
);
</script>
