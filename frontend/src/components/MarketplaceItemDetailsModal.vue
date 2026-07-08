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
                class="mb-5 rounded-xl border border-amber-400 bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E] leading-relaxed"
                role="note"
              >
                <p class="font-semibold text-[#78350F]">Preview only: in-person purchase at the event.</p>
                <p class="mt-1">
                  This item is shown to help you plan your visit. Please go to the vendor booth during the CMart
                  Carboot event to view, confirm availability, and purchase in person.
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
              </div>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../services/api';
import ReuseItemImageGallery from './ReuseItemImageGallery.vue';
import { normalizeReuseItem } from '../utils/imageUrl';
import { formatItemPrice } from '../utils/vendorCatalog';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue']);

const item = ref(null);
const loading = ref(false);
const loadError = ref(false);

const close = () => emit('update:modelValue', false);

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

watch(
  () => [props.modelValue, props.itemId],
  ([open, id]) => {
    if (open && id) loadItem();
    if (!open) {
      item.value = null;
    }
  },
);
</script>
