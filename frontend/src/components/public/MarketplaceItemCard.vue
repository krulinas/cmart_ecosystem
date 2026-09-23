<template>
  <article
    data-testid="marketplace-item-card"
    :data-item-id="item.id"
    class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md"
  >
    <button
      type="button"
      class="block w-full text-left"
      :aria-label="t('marketplace.card.viewDetailsAria', { name: item.name })"
      @click="$emit('select', item)"
    >
      <div class="aspect-[4/3] overflow-hidden bg-gradient-to-br from-brand-50 via-sky-50 to-cyan-50">
        <img
          v-if="item.image_url"
          :src="item.image_url"
          :alt="item.name"
          class="h-full w-full object-cover"
        />
        <div v-else class="flex h-full items-center justify-center text-xs font-bold uppercase tracking-wider text-brand-400">
          {{ t('marketplace.card.noImage') }}
        </div>
      </div>
    </button>

    <div class="flex flex-1 flex-col p-5">
      <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ categoryLabel }}</p>
      <h3
        class="mt-1 text-lg font-extrabold text-gray-900 line-clamp-2"
        data-testid="marketplace-item-title"
      >
        {{ item.name }}
      </h3>

      <dl class="mt-3 space-y-1 text-sm text-gray-600">
        <div class="flex justify-between gap-3">
          <dt class="text-gray-500">{{ t('marketplace.card.condition') }}</dt>
          <dd class="font-semibold text-gray-800">{{ item.condition }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-gray-500">{{ t('marketplace.card.budgetGuide') }}</dt>
          <dd class="font-semibold text-brand-700">{{ priceLabel }}</dd>
        </div>
      </dl>

      <p class="mt-3 text-xs font-semibold text-emerald-700">
        {{ t('marketplace.card.availableAt') }}
        <span v-if="item.event?.date_label"> · {{ item.event.date_label }}</span>
      </p>
      <p class="mt-1 text-xs text-gray-500">{{ t('marketplace.card.purchaseInPerson') }}</p>

      <button
        type="button"
        class="mt-5 ml-btn-ghost w-full text-sm"
        @click="$emit('select', item)"
      >
        {{ t('marketplace.card.viewDetails') }}
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatItemPrice } from '../../utils/vendorCatalog';

const props = defineProps({
  item: { type: Object, required: true },
});

defineEmits(['select']);

const { t } = useI18n();

const CATEGORY_KEYS = {
  'Pre-loved / Thrift': 'marketplace.categories.preloved',
  'Food & Beverages': 'marketplace.categories.food',
  'Clothing & Apparel': 'marketplace.categories.clothing',
  'Handicrafts & Art': 'marketplace.categories.handicrafts',
  'Electronics & Gadgets': 'marketplace.categories.electronics',
  Others: 'marketplace.categories.others',
};

const categoryLabel = computed(() => {
  const key = CATEGORY_KEYS[props.item?.category];
  return key ? t(key) : props.item?.category;
});

const priceLabel = computed(() => {
  if (props.item?.pricing_type === 'free') return t('marketplace.pricing.free');
  if (props.item?.pricing_type === 'donation') return t('marketplace.pricing.donation');
  return formatItemPrice(props.item);
});
</script>
