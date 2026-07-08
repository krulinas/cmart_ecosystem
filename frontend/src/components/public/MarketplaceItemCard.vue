<template>
  <article
    data-testid="marketplace-item-card"
    class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md"
  >
    <button
      type="button"
      class="block w-full text-left"
      :aria-label="`View details for ${item.name}`"
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
          No image
        </div>
      </div>
    </button>

    <div class="flex flex-1 flex-col p-5">
      <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ item.category }}</p>
      <h3
        class="mt-1 text-lg font-extrabold text-gray-900 line-clamp-2"
        data-testid="marketplace-item-title"
      >
        {{ item.name }}
      </h3>

      <dl class="mt-3 space-y-1 text-sm text-gray-600">
        <div class="flex justify-between gap-3">
          <dt class="text-gray-500">Condition</dt>
          <dd class="font-semibold text-gray-800">{{ item.condition }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-gray-500">Budget Guide</dt>
          <dd class="font-semibold text-brand-700">{{ formatItemPrice(item) }}</dd>
        </div>
      </dl>

      <p class="mt-3 text-xs font-semibold text-emerald-700">
        Available at CMart Carboot
        <span v-if="item.event?.date_label"> · {{ item.event.date_label }}</span>
      </p>
      <p class="mt-1 text-xs text-gray-500">Purchase: In-person only</p>

      <button
        type="button"
        class="mt-5 ml-btn-ghost w-full text-sm"
        @click="$emit('select', item)"
      >
        View Details
      </button>
    </div>
  </article>
</template>

<script setup>
import { formatItemPrice } from '../../utils/vendorCatalog';

defineProps({
  item: { type: Object, required: true },
});

defineEmits(['select']);
</script>
