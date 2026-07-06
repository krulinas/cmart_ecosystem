<template>
  <div>
    <p :class="enClass">{{ textEn }}</p>
    <template v-if="textMs">
      <template v-if="collapsibleMalay">
        <button
          type="button"
          class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1 rounded"
          :aria-expanded="malayOpen"
          :aria-controls="malayPanelId"
          :aria-label="malayOpen ? 'Hide BM explanation' : 'Show BM explanation'"
          @click="malayOpen = !malayOpen"
        >
          <span>{{ buttonLabel }}</span>
          <svg
            class="h-3.5 w-3.5 shrink-0 transition-transform"
            :class="malayOpen ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <p v-if="malayOpen" :id="malayPanelId" :class="msClass">{{ textMs }}</p>
      </template>
      <p v-else :class="msClass">{{ textMs }}</p>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  textEn: { type: String, required: true },
  textMs: { type: String, default: '' },
  enClass: { type: String, default: 'text-[15px] leading-7 text-slate-700' },
  msClass: { type: String, default: 'mt-2 text-[13px] leading-6 text-slate-500 font-normal' },
  collapsibleMalay: { type: Boolean, default: true },
  defaultMalayOpen: { type: Boolean, default: false },
  buttonLabel: { type: String, default: 'BM explanation' },
});

const malayOpen = ref(props.defaultMalayOpen);
const malayPanelId = `bilingual-ms-${Math.random().toString(36).slice(2, 9)}`;
</script>
