<template>
  <span ref="rootRef" class="relative inline-flex align-middle">
    <button
      type="button"
      class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-ink-400 transition hover:bg-brand-50 hover:text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1"
      :aria-label="ariaLabel"
      :aria-expanded="open"
      @click="toggle"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
      </svg>
    </button>

    <div
      v-if="open"
      :id="tooltipId"
      role="tooltip"
      class="absolute z-30 mt-1.5 w-60 sm:w-72 rounded-xl border border-brand-100 bg-white p-3.5 text-left shadow-lg ring-1 ring-black/5"
      :class="placementClass"
      @keydown.escape.stop="close"
    >
      <p class="text-[15px] leading-7 text-slate-700">{{ textEn }}</p>
      <p v-if="textMs && SHOW_BM_COPY" class="mt-2 text-[13px] leading-6 text-slate-500 font-normal">{{ textMs }}</p>
    </div>
  </span>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { SHOW_BM_COPY } from '../config/locale';

const props = defineProps({
  textEn: { type: String, required: true },
  textMs: { type: String, default: '' },
  ariaLabel: { type: String, default: 'What is this?' },
  placement: { type: String, default: 'bottom-left' },
});

const open = ref(false);
const rootRef = ref(null);
const tooltipId = `info-tip-${Math.random().toString(36).slice(2, 9)}`;

const placementClass = computed(() => {
  if (props.placement === 'bottom-right') {
    return 'right-0 top-full';
  }
  return 'left-0 top-full';
});

const toggle = () => {
  open.value = !open.value;
};

const close = () => {
  open.value = false;
};

const onDocumentClick = (event) => {
  if (!open.value) return;
  if (rootRef.value && !rootRef.value.contains(event.target)) {
    close();
  }
};

onMounted(() => {
  document.addEventListener('click', onDocumentClick, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick, true);
});
</script>
