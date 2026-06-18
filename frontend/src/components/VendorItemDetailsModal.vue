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
        v-if="modelValue && item"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="vendor-item-details-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden" @click.stop>
          <ReuseItemImageGallery :item="item" :alt-text="item.name" placeholder-text="No image" />

          <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 id="vendor-item-details-title" class="text-xl font-extrabold text-ink-900">{{ item.name }}</h2>
                <p class="text-sm text-brand-700 font-semibold mt-1">{{ item.category }}</p>
              </div>
              <span :class="statusClass">{{ item.status }}</span>
            </div>

            <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Condition</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ item.condition }}</dd>
              </div>
              <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Price</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ formatItemPrice(item) }}</dd>
              </div>
              <div class="sm:col-span-2 rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Listed on</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ listedDate }}</dd>
              </div>
              <div v-if="item.description" class="sm:col-span-2 rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Description</dt>
                <dd class="mt-1 text-ink-700 whitespace-pre-line">{{ item.description }}</dd>
              </div>
            </dl>

            <div class="mt-6 flex flex-wrap gap-2">
              <button type="button" class="ml-btn-primary" @click="$emit('edit', item)">Edit Item</button>
              <button type="button" class="ml-btn-ghost" @click="close">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import ReuseItemImageGallery from './ReuseItemImageGallery.vue';
import { formatItemPrice } from '../utils/vendorCatalog';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'edit']);

const close = () => emit('update:modelValue', false);

const statusClass = computed(() =>
  props.item?.status === 'active'
    ? 'ml-badge bg-emerald-100 text-emerald-800 capitalize'
    : 'ml-badge bg-ink-100 text-ink-700 capitalize',
);

const listedDate = computed(() => {
  if (!props.item?.created_at) return '—';
  const date = new Date(props.item.created_at);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
});
</script>
