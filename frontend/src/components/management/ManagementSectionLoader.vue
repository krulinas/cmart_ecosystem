<template>
  <div class="flex flex-col items-center justify-center rounded-2xl border border-ink-200 bg-white py-20 text-center shadow-sm">
    <div class="h-10 w-10 animate-pulse rounded-full bg-gradient-to-br from-cyan-100 to-sky-100" />
    <p class="mt-4 text-sm font-medium text-ink-500">{{ message }}</p>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Loading section data…' },
});
</script>
