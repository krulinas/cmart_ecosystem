<template>
  <div class="space-y-1">
    <p :class="enClass">{{ textEn }}</p>
    <p v-if="textMs" :class="msClass">{{ textMs }}</p>
  </div>
</template>

<script setup>
defineProps({
  textEn: { type: String, required: true },
  textMs: { type: String, default: '' },
  enClass: { type: String, default: 'text-readable-sm text-ink-600 leading-relaxed' },
  msClass: { type: String, default: 'text-sm text-ink-400 leading-relaxed' },
});
</script>
