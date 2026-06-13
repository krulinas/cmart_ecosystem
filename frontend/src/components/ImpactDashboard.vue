<template>
  <section
    id="impact"
    ref="sectionRef"
    class="scroll-mt-24 bg-gray-50 border-b border-gray-100 py-12 sm:py-16 px-4 sm:px-6"
  >
    <div class="max-w-7xl mx-auto">
      <div class="mb-8 sm:mb-10" :class="headerRevealClass('fade')">
        <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Live Analytics</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">Our Impact</h2>
        <p class="mt-2 text-gray-600 max-w-2xl">
          Real-time sustainability and community metrics from Carboot@CMart events.
        </p>
      </div>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div
          v-for="n in 3"
          :key="n"
          class="bg-white rounded-2xl border border-gray-100 p-6 animate-pulse"
        >
          <div class="h-10 w-10 bg-brand-100 rounded-xl mb-4"></div>
          <div class="h-8 w-24 bg-gray-200 rounded mb-2"></div>
          <div class="h-4 w-32 bg-gray-100 rounded"></div>
        </div>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div
          v-for="(card, index) in cards"
          :key="card.key"
          class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 ease-out relative overflow-hidden group"
          :class="staggerCardClass(cardsVisible, index)"
          :style="staggerCardStyle(cardsVisible, index)"
        >
          <div class="absolute top-0 left-0 w-full h-1 bg-brand-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-xl mb-5 border"
            :class="card.iconClass"
          >
            <component :is="card.icon" />
          </div>
          <p class="text-3xl sm:text-4xl font-black text-gray-900 tabular-nums leading-none mb-2">
            {{ card.displayValue }}
          </p>
          <p class="text-sm font-bold text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
          <p v-if="card.subtext" class="mt-2 text-xs text-gray-400">{{ card.subtext }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, h } from 'vue';
import { useScrollReveal } from '../composables/useScrollReveal';

const props = defineProps({
  reusedItems: { type: Number, default: 12450 },
  economicValueRm: { type: Number, default: 892000 },
  activeVendors: { type: Number, default: 156 },
  activeStudents: { type: Number, default: 89 },
  loading: { type: Boolean, default: false },
});

const { targetRef: sectionRef, isVisible: cardsVisible, revealClass: headerRevealClass } = useScrollReveal({
  threshold: 0.08,
});

const formatCount = (value) => new Intl.NumberFormat('en-MY').format(value);

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 0 }).format(value);

const LeafIcon = () =>
  h('svg', { class: 'w-6 h-6', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    }),
  ]);

const CurrencyIcon = () =>
  h('svg', { class: 'w-6 h-6', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    }),
  ]);

const PeopleIcon = () =>
  h('svg', { class: 'w-6 h-6', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', {
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      'stroke-width': '2',
      d: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    }),
  ]);

const cards = computed(() => [
  {
    key: 'reused',
    label: 'Reused Items',
    displayValue: formatCount(props.reusedItems),
    subtext: 'Items kept out of landfills',
    icon: LeafIcon,
    iconClass: 'bg-emerald-50 text-emerald-600 border-emerald-100',
  },
  {
    key: 'economic',
    label: 'Economic Value Generated',
    displayValue: formatCurrency(props.economicValueRm),
    subtext: 'Circular economy turnover',
    icon: CurrencyIcon,
    iconClass: 'bg-brand-50 text-brand-600 border-brand-100',
  },
  {
    key: 'participation',
    label: 'Active Participation',
    displayValue: `${formatCount(props.activeVendors)} / ${formatCount(props.activeStudents)}`,
    subtext: 'Vendors · UUM students',
    icon: PeopleIcon,
    iconClass: 'bg-violet-50 text-violet-600 border-violet-100',
  },
]);

const staggerCardClass = (visible, index) => {
  const motionSafe = 'motion-reduce:opacity-100 motion-reduce:translate-y-0';
  return visible ? `opacity-100 translate-y-0 ${motionSafe}` : `opacity-0 translate-y-8 ${motionSafe}`;
};

const staggerCardStyle = (visible, index) => ({
  transitionDelay: visible ? `${Math.min(index * 75, 400)}ms` : '0ms',
});
</script>
