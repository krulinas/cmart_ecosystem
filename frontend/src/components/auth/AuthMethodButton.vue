<template>
  <button
    type="button"
    class="w-full flex justify-center items-center gap-3 py-3 px-4 rounded-xl text-sm font-semibold transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
    :class="variant === 'google'
      ? 'border border-ink-200 bg-white text-ink-800 shadow-sm hover:bg-ink-50'
      : 'border border-brand-200 bg-brand-50 text-brand-800 hover:bg-brand-100'"
    :data-testid="testId"
    @click="$emit('click')"
  >
    <slot name="icon" />
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'google'].includes(value),
  },
  testId: { type: String, default: null },
});

defineEmits(['click']);
</script>
