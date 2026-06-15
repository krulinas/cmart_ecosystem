<template>
  <div
    class="rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md"
    :class="[ringClass, borderClass]"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">{{ title }}</p>
        <p v-if="description" class="mt-1 text-xs text-ink-400 leading-relaxed">{{ description }}</p>
      </div>
      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white shadow-sm"
        :class="iconBgClass"
      >
        {{ icon }}
      </div>
    </div>
    <div class="mt-4 flex items-end gap-2">
      <span class="text-3xl font-extrabold tracking-tight" :class="valueClass">{{ value }}</span>
      <span v-if="suffix" class="mb-1 text-sm font-medium text-ink-400">{{ suffix }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  value: { type: [Number, String], default: 0 },
  suffix: { type: String, default: '' },
  icon: { type: String, default: '·' },
  accent: { type: String, default: 'cyan' },
});

const accentMap = {
  cyan: {
    icon: 'bg-gradient-to-br from-cyan-500 to-sky-500',
    value: 'text-cyan-700',
    ring: 'ring-cyan-100',
    border: 'border-cyan-100/80',
  },
  sky: {
    icon: 'bg-gradient-to-br from-sky-500 to-blue-500',
    value: 'text-sky-700',
    ring: 'ring-sky-100',
    border: 'border-sky-100/80',
  },
  amber: {
    icon: 'bg-gradient-to-br from-amber-500 to-orange-500',
    value: 'text-amber-800',
    ring: 'ring-amber-100',
    border: 'border-amber-100/80',
  },
  emerald: {
    icon: 'bg-gradient-to-br from-emerald-500 to-teal-500',
    value: 'text-emerald-700',
    ring: 'ring-emerald-100',
    border: 'border-emerald-100/80',
  },
  slate: {
    icon: 'bg-gradient-to-br from-slate-700 to-slate-900',
    value: 'text-slate-800',
    ring: 'ring-slate-200',
    border: 'border-slate-200/80',
  },
  rose: {
    icon: 'bg-gradient-to-br from-rose-500 to-pink-500',
    value: 'text-rose-700',
    ring: 'ring-rose-100',
    border: 'border-rose-100/80',
  },
};

const palette = computed(() => accentMap[props.accent] ?? accentMap.cyan);
const iconBgClass = computed(() => palette.value.icon);
const valueClass = computed(() => palette.value.value);
const ringClass = computed(() => `ring-1 ${palette.value.ring}`);
const borderClass = computed(() => palette.value.border);
</script>
