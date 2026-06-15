<template>
  <div class="rounded-2xl border border-dashed border-ink-200 bg-ink-50/50 px-6 py-12 text-center">
    <div
      class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-bold text-white shadow-sm"
      :class="iconBgClass"
    >
      {{ icon }}
    </div>
    <h3 class="text-base font-bold text-ink-900">{{ title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-ink-500">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
  icon: { type: String, default: '○' },
  accent: { type: String, default: 'cyan' },
});

const iconBgClass = computed(() => {
  const map = {
    cyan: 'bg-gradient-to-br from-cyan-500 to-sky-500',
    sky: 'bg-gradient-to-br from-sky-500 to-blue-500',
    amber: 'bg-gradient-to-br from-amber-500 to-orange-500',
  };
  return map[props.accent] ?? map.cyan;
});
</script>
