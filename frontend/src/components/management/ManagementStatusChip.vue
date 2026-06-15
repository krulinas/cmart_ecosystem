<template>
  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="chipClass">
    <span class="h-1.5 w-1.5 rounded-full" :class="dotClass" />
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { statusLabel } from '../../utils/bookingDisplay';

const props = defineProps({
  status: { type: String, required: true },
});

const label = computed(() => {
  const map = {
    Pending_Staff: 'Awaiting Staff',
    Pending_Boss: 'Awaiting Manager',
    Needs_Revision: 'Needs Revision',
    Approved: 'Approved',
    Rejected: 'Rejected',
    Cancelled: 'Cancelled',
  };
  return map[props.status] || statusLabel(props.status);
});

const chipClass = computed(() => {
  const map = {
    Pending_Staff: 'bg-cyan-50 text-cyan-800 ring-1 ring-cyan-200/80',
    Pending_Boss: 'bg-sky-50 text-sky-800 ring-1 ring-sky-200/80',
    Needs_Revision: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200/80',
    Approved: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/80',
    Rejected: 'bg-rose-50 text-rose-800 ring-1 ring-rose-200/80',
    Cancelled: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/80',
  };
  return map[props.status] || 'bg-ink-100 text-ink-700 ring-1 ring-ink-200';
});

const dotClass = computed(() => {
  const map = {
    Pending_Staff: 'bg-cyan-500',
    Pending_Boss: 'bg-sky-500',
    Needs_Revision: 'bg-amber-500',
    Approved: 'bg-emerald-500',
    Rejected: 'bg-rose-500',
    Cancelled: 'bg-slate-400',
  };
  return map[props.status] || 'bg-ink-400';
});
</script>
