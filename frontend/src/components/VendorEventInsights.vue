<template>
  <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
    <div class="mb-6 sm:mb-8">
      <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Personal Analytics</span>
      <h2 class="text-xl sm:text-2xl font-extrabold text-ink-900 tracking-tight">My Event Insights</h2>
      <p class="mt-1 text-sm text-ink-500 max-w-2xl">
        Personal analytics based on your booth activity and event participation.
      </p>
    </div>

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
      <div
        v-for="n in 3"
        :key="n"
        class="rounded-2xl border border-ink-100 bg-white/70 p-6 animate-pulse"
      >
        <div class="h-10 w-10 bg-brand-100 rounded-xl mb-4"></div>
        <div class="h-8 w-24 bg-ink-200 rounded mb-2"></div>
        <div class="h-4 w-32 bg-ink-100 rounded"></div>
      </div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load your event insights right now.</p>
      <p class="mt-1 text-xs text-amber-800">Showing safe defaults until data is available.</p>
      <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="$emit('retry')">Try Again</button>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
      <div
        v-for="card in cards"
        :key="card.key"
        class="rounded-2xl border border-ink-100 bg-white/70 p-6 sm:p-7 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 relative overflow-hidden group"
      >
        <div class="absolute top-0 left-0 w-full h-1 bg-brand-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div
          class="flex h-11 w-11 items-center justify-center rounded-xl mb-4 border"
          :class="card.iconClass"
        >
          <component :is="card.icon" />
        </div>
        <p class="text-2xl sm:text-3xl font-black text-ink-900 tabular-nums leading-none mb-2">
          {{ card.displayValue }}
        </p>
        <p class="text-sm font-bold text-ink-500 uppercase tracking-wide">{{ card.label }}</p>
        <p v-if="card.subtext" class="mt-2 text-xs text-ink-400">{{ card.subtext }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, h } from 'vue';

const props = defineProps({
  itemsReused: { type: Number, default: 0 },
  estimatedSales: { type: Number, default: 0 },
  boothStatus: { type: String, default: 'No Active Booking' },
  currentEvent: { type: String, default: null },
  boothNumber: { type: String, default: null },
  loading: { type: Boolean, default: false },
  loadError: { type: Boolean, default: false },
});

defineEmits(['retry']);

const formatCount = (value) => new Intl.NumberFormat('en-MY').format(value ?? 0);

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value ?? 0);

const LeafIcon = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    }),
  ]);

const CurrencyIcon = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    }),
  ]);

const BoothIcon = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    }),
  ]);

const boothStatusClass = (status) => {
  if (status === 'Checked-in') return 'bg-emerald-50 text-emerald-700 border-emerald-100';
  if (status === 'Approved') return 'bg-brand-50 text-brand-700 border-brand-100';
  if (status === 'Pending') return 'bg-amber-50 text-amber-700 border-amber-100';
  return 'bg-ink-50 text-ink-600 border-ink-100';
};

const boothSubtext = computed(() => {
  const parts = ['Current event booth activity'];
  if (props.currentEvent) parts.push(props.currentEvent);
  if (props.boothNumber) parts.push(`Booth ${props.boothNumber}`);
  return parts.join(' · ');
});

const cards = computed(() => [
  {
    key: 'items',
    label: 'My Items Reused',
    displayValue: formatCount(props.itemsReused),
    subtext: 'Items circulated from your booth',
    icon: LeafIcon,
    iconClass: 'bg-emerald-50 text-emerald-600 border-emerald-100',
  },
  {
    key: 'sales',
    label: 'My Estimated Sales',
    displayValue: formatCurrency(props.estimatedSales),
    subtext: 'Based on your recorded transactions',
    icon: CurrencyIcon,
    iconClass: 'bg-brand-50 text-brand-600 border-brand-100',
  },
  {
    key: 'booth',
    label: 'My Booth Status',
    displayValue: props.boothStatus,
    subtext: boothSubtext.value,
    icon: BoothIcon,
    iconClass: boothStatusClass(props.boothStatus),
  },
]);
</script>
