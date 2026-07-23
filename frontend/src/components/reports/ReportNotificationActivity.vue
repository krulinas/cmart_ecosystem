<template>
  <div v-if="items.length" class="mt-3 rounded-xl border border-ink-100 bg-ink-50/70 p-3">
    <h4 class="text-xs font-bold uppercase tracking-wider text-ink-500">Notification activity</h4>
    <p class="mt-1 text-[11px] leading-relaxed text-ink-500">
      Email and WhatsApp alerts shown here are simulations for the current prototype. No external message was sent.
    </p>
    <ul class="mt-2 space-y-1.5 text-sm text-ink-700">
      <li v-for="row in items" :key="row.id || `${row.action}-${row.created_at}-${row.channel}`" class="flex flex-wrap items-start gap-2">
        <span aria-hidden="true">{{ glyph(row) }}</span>
        <span class="min-w-0 flex-1">
          <span class="font-medium">{{ row.label }}</span>
          <span v-if="row.recipient_masked" class="text-ink-500"> · {{ row.recipient_masked }}</span>
          <span v-if="row.skip_reason" class="block text-xs text-ink-500">{{ row.skip_reason }}</span>
          <span v-if="row.created_at" class="block text-[11px] text-ink-400">{{ formatDate(row.created_at) }}</span>
        </span>
      </li>
    </ul>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
});

const glyph = (row) => {
  if (row.kind === 'external_simulation') {
    return row.status === 'skipped' ? '○' : '◉';
  }
  return '✓';
};

const formatDate = (value) => {
  if (!value) return '';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};
</script>
