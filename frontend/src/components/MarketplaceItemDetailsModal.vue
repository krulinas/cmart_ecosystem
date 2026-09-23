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
          <div v-if="loading" class="p-10 text-center text-ink-500">{{ t('marketplace.details.loading') }}</div>

          <div v-else-if="loadError" class="p-10 text-center">
            <p class="text-sm text-rose-700 font-semibold">{{ t('marketplace.details.loadError') }}</p>
            <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="loadItem">{{ t('marketplace.details.tryAgain') }}</button>
          </div>

          <template v-else-if="item">
            <ReuseItemImageGallery :item="item" :alt-text="item.name" />

            <div class="p-6 sm:p-8">
              <div
                class="mb-5 rounded-xl border px-4 py-3 text-sm leading-relaxed"
                :class="policyToneClass"
                role="note"
              >
                <p class="font-semibold" :class="policyTitleClass">{{ policyTitle }}</p>
                <p class="mt-1">{{ policyBody }}</p>
              </div>

              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ categoryLabel }}</p>
                  <h2 id="marketplace-item-details-title" class="mt-1 text-2xl font-extrabold text-ink-900">{{ item.name }}</h2>
                  <p class="mt-2 text-sm text-emerald-700 font-medium">
                    {{ t('marketplace.details.availableAt') }}
                    <span v-if="item.event?.date_label"> · {{ item.event.date_label }}</span>
                    {{ t('marketplace.details.purchaseInPersonSuffix') }}
                  </p>
                  <p
                    v-if="item.has_active_reservation"
                    class="mt-2 text-sm font-semibold text-amber-700"
                    data-testid="marketplace-item-already-reserved"
                  >
                    {{ t('marketplace.details.alreadyReserved') }}
                  </p>
                </div>
                <p class="text-lg font-black text-brand-700 shrink-0">
                  {{ t('marketplace.details.guidePricePrefix') }} {{ priceLabel }}
                </p>
              </div>

              <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('marketplace.details.condition') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ item.condition }}</dd>
                </div>
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('marketplace.details.budgetGuide') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900 capitalize">{{ pricingTypeLabel }}</dd>
                </div>
                <div
                  v-if="item.reservation_service_fee != null"
                  class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 sm:col-span-2"
                  data-testid="marketplace-reservation-fee"
                >
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('marketplace.details.reservationFee') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">
                    {{ formatReservationFee(item.reservation_service_fee, item.reservation_service_fee_currency) }}
                  </dd>
                </div>
              </dl>

              <div v-if="item.description" class="mt-4 rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('marketplace.details.description') }}</h3>
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
                    <span v-else class="text-[10px] font-bold uppercase text-ink-400">{{ t('marketplace.details.vendor') }}</span>
                  </div>
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('marketplace.details.vendor') }}</p>
                    <h3 class="text-lg font-bold text-ink-900">{{ item.vendor?.business_name || t('marketplace.details.cmartVendor') }}</h3>
                    <p v-if="item.vendor?.business_category" class="text-sm text-brand-700 font-semibold mt-0.5">
                      {{ item.vendor.business_category }}
                    </p>
                    <p v-if="item.vendor?.description" class="mt-2 text-sm text-ink-600 line-clamp-4">
                      {{ item.vendor.description }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="mt-6 flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-end gap-3">
                <button type="button" class="ml-btn-ghost" @click="close">{{ t('marketplace.details.close') }}</button>
                <a
                  v-if="whatsappContact"
                  :href="whatsappContact.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="ml-btn-ghost"
                  data-testid="marketplace-whatsapp-contact"
                  :aria-label="t('marketplace.details.contactWhatsAppAria', { name: item.vendor?.business_name || t('marketplace.details.vendor') })"
                >
                  {{ t('marketplace.details.contactWhatsApp') }}
                </a>
                <router-link
                  v-if="reserveMode === 'login'"
                  :to="loginHref"
                  class="ml-btn-primary"
                  data-testid="marketplace-reserve-login"
                >
                  {{ t('marketplace.details.logInToReserve') }}
                </router-link>
                <button
                  v-else-if="reserveMode === 'reserve'"
                  type="button"
                  class="ml-btn-primary"
                  data-testid="marketplace-reserve-cta"
                  @click="showReserveModal = true"
                >
                  {{ t('marketplace.details.reserve') }}
                </button>
                <button
                  v-else-if="reserveMode === 'already_reserved'"
                  type="button"
                  class="ml-btn-primary"
                  disabled
                  data-testid="marketplace-already-reserved"
                >
                  {{ t('marketplace.details.alreadyReservedCta') }}
                </button>
                <button
                  v-else-if="reserveMode === 'not_configured'"
                  type="button"
                  class="ml-btn-primary"
                  disabled
                  data-testid="marketplace-reservation-unavailable"
                >
                  {{ t('marketplace.details.reservationUnavailable') }}
                </button>
                <p
                  v-else-if="reserveMode === 'own_item'"
                  class="text-sm font-semibold text-ink-500"
                  data-testid="marketplace-own-listing"
                >
                  {{ t('marketplace.details.yourListing') }}
                </p>
                <p
                  v-else-if="reserveMode === 'ineligible_role'"
                  class="text-sm font-semibold text-ink-500"
                  data-testid="marketplace-reservation-ineligible"
                >
                  {{ t('marketplace.details.communityOnly') }}
                </p>
                <button
                  v-else
                  type="button"
                  class="ml-btn-primary"
                  disabled
                  data-testid="marketplace-reservation-closed"
                >
                  {{ t('marketplace.details.reservationUnavailable') }}
                </button>
              </div>
              <p
                v-if="reserveMode === 'not_configured'"
                class="mt-3 text-sm text-ink-500 sm:text-right"
              >
                {{ t('marketplace.details.inPersonOnlyNote') }}
              </p>
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
import { useI18n } from 'vue-i18n';
import api from '../services/api';
import ReuseItemImageGallery from './ReuseItemImageGallery.vue';
import ItemReservationConfirmModal from './ItemReservationConfirmModal.vue';
import { normalizeReuseItem } from '../utils/imageUrl';
import { formatItemPrice } from '../utils/vendorCatalog';
import { formatReservationFee, reserveCtaMode } from '../utils/itemReservationDisplay';
import { vendorWhatsappContact } from '../utils/whatsappContact';
import { loginPathWithRedirect } from '../utils/postAuthRedirect';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [Number, String], default: null },
  carbootEventId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'reserved']);
const { t } = useI18n();

const auth = useAuthStore();
const item = ref(null);
const loading = ref(false);
const loadError = ref(false);
const showReserveModal = ref(false);

const CATEGORY_KEYS = {
  'Pre-loved / Thrift': 'marketplace.categories.preloved',
  'Food & Beverages': 'marketplace.categories.food',
  'Clothing & Apparel': 'marketplace.categories.clothing',
  'Handicrafts & Art': 'marketplace.categories.handicrafts',
  'Electronics & Gadgets': 'marketplace.categories.electronics',
  Others: 'marketplace.categories.others',
};

const close = () => emit('update:modelValue', false);

const reserveMode = computed(() => reserveCtaMode({
  item: item.value,
  isAuthenticated: auth.isAuthenticated,
  isCommunityMember: auth.isCommunityMember,
  isCmartWorker: auth.isCmartWorker,
}));

const whatsappContact = computed(() => vendorWhatsappContact(item.value));

const categoryLabel = computed(() => {
  const key = CATEGORY_KEYS[item.value?.category];
  return key ? t(key) : item.value?.category;
});

const priceLabel = computed(() => {
  if (item.value?.pricing_type === 'free') return t('marketplace.pricing.free');
  if (item.value?.pricing_type === 'donation') return t('marketplace.pricing.donation');
  return formatItemPrice(item.value);
});

const pricingTypeLabel = computed(() => {
  const type = item.value?.pricing_type;
  if (type === 'free') return t('marketplace.pricing.free');
  if (type === 'donation') return t('marketplace.pricing.donation');
  return String(type || '').replace('_', ' ');
});

const policyTitle = computed(() => {
  if (reserveMode.value === 'already_reserved') return t('marketplace.details.policyHoldTitle');
  if (reserveMode.value === 'not_configured') return t('marketplace.details.policyInPersonTitle');
  if (reserveMode.value === 'reserve' || reserveMode.value === 'login') {
    return t('marketplace.details.policyReserveTitle');
  }
  return t('marketplace.details.policyBrowseTitle');
});

const policyBody = computed(() => {
  if (reserveMode.value === 'already_reserved') return t('marketplace.details.policyHoldBody');
  if (reserveMode.value === 'not_configured') return t('marketplace.details.policyInPersonBody');
  if (reserveMode.value === 'reserve' || reserveMode.value === 'login') {
    return t('marketplace.details.policyReserveBody');
  }
  return t('marketplace.details.policyBrowseBody');
});

const policyToneClass = computed(() => (
  reserveMode.value === 'reserve' || reserveMode.value === 'login'
    ? 'border-emerald-300 bg-emerald-50 text-emerald-950'
    : 'border-amber-400 bg-[#FFFBEB] text-[#92400E]'
));

const policyTitleClass = computed(() => (
  reserveMode.value === 'reserve' || reserveMode.value === 'login'
    ? 'text-emerald-900'
    : 'text-[#78350F]'
));

const loginHref = computed(() => {
  const params = new URLSearchParams();
  if (props.itemId) params.set('item', String(props.itemId));
  if (props.carbootEventId) params.set('event', String(props.carbootEventId));
  const query = params.toString();
  return loginPathWithRedirect(query ? `/marketplace?${query}` : '/marketplace');
});

const loadItem = async () => {
  if (!props.itemId) return;
  loading.value = true;
  loadError.value = false;
  try {
    const { data } = await api.get(`/marketplace/items/${props.itemId}`, {
      params: { carboot_event_id: props.carbootEventId || undefined },
    });
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
  () => [props.modelValue, props.itemId, props.carbootEventId],
  ([open, id]) => {
    if (open && id) loadItem();
    if (!open) {
      item.value = null;
      showReserveModal.value = false;
    }
  },
);
</script>
