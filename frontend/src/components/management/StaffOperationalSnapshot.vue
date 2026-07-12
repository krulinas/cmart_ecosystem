<template>
  <section
    data-testid="staff-operational-snapshot"
    class="scroll-mt-24 bg-gray-50 border-b border-gray-100 py-12 sm:py-16 px-4 sm:px-6"
  >
    <div class="max-w-7xl mx-auto">
      <div class="mb-8 sm:mb-10">
        <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Operations</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">Operational Snapshot</h2>
        <p class="mt-2 text-gray-600 max-w-2xl">
          Quick overview of items that may need organizer attention.
        </p>
      </div>

      <div
        v-if="loading"
        class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6"
        data-testid="staff-operational-snapshot-loading"
      >
        <div
          v-for="n in 5"
          :key="n"
          class="bg-white rounded-2xl border border-gray-100 p-6 animate-pulse"
        >
          <div class="h-10 w-10 bg-brand-100 rounded-xl mb-4" />
          <div class="h-8 w-16 bg-gray-200 rounded mb-2" />
          <div class="h-4 w-40 bg-gray-100 rounded mb-1" />
          <div class="h-3 w-56 bg-gray-50 rounded" />
        </div>
      </div>

      <div
        v-else-if="loadError"
        class="rounded-2xl border border-rose-200 bg-rose-50/60 px-6 py-10 text-center"
        data-testid="staff-operational-snapshot-error"
      >
        <p class="text-sm font-semibold text-rose-800">Unable to load operational snapshot</p>
        <p class="mt-1 text-sm text-rose-700/80">{{ loadError }}</p>
        <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="load">Try again</button>
      </div>

      <template v-else>
        <div
          v-if="visibleCards.length === 0"
          class="rounded-2xl border border-gray-100 bg-white px-6 py-12 text-center text-sm text-gray-500"
          data-testid="staff-operational-snapshot-empty"
        >
          No operational items need attention right now.
        </div>

        <div
          v-else
          class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6"
        >
          <RouterLink
            v-for="card in visibleCards"
            :key="card.key"
            :to="{ path: '/admin', hash: `#${card.hash}` }"
            :aria-label="cardAriaLabel(card)"
            :data-testid="`staff-operational-card-${card.key}`"
            class="group block rounded-2xl border border-gray-100 bg-white p-6 sm:p-8 shadow-sm transition-all duration-300 ease-out relative overflow-hidden cursor-pointer text-inherit no-underline hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 hover:border-brand-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 active:scale-[0.99] motion-reduce:transform-none motion-reduce:active:scale-100"
          >
            <div class="absolute top-0 left-0 w-full h-1 bg-brand-500 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity" />
            <div
              class="flex h-12 w-12 items-center justify-center rounded-xl mb-5 border text-lg font-bold"
              :class="card.iconClass"
            >
              {{ card.icon }}
            </div>
            <p class="text-3xl sm:text-4xl font-black text-gray-900 tabular-nums leading-none mb-2">
              {{ formatCount(card.value) }}
            </p>
            <p class="text-sm font-bold text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
            <p class="mt-2 text-xs text-gray-400">{{ card.description }}</p>
            <span class="mt-4 inline-flex text-xs font-semibold text-brand-600 group-hover:text-brand-700">
              Open {{ card.linkLabel }} →
            </span>
          </RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../services/api';

const loading = ref(false);
const loadError = ref(null);
const summary = ref(null);
const hasLoaded = ref(false);

const formatCount = (value) => new Intl.NumberFormat('en-MY').format(value ?? 0);

const cardAriaLabel = (card) => `Open ${card.linkLabel} for ${card.label}`;

const cardDefinitions = [
  {
    key: 'pending_organizer_review',
    label: 'Pending Organizer Review',
    description: 'Booking requests awaiting direct organizer review.',
    icon: 'Bk',
    iconClass: 'bg-cyan-50 text-cyan-600 border-cyan-100',
    hash: 'bookings',
    linkLabel: 'Bookings',
  },
  {
    key: 'needs_revision',
    label: 'Needs Revision',
    description: 'Bookings returned for vendor correction or follow-up.',
    icon: 'Rv',
    iconClass: 'bg-amber-50 text-amber-600 border-amber-100',
    hash: 'bookings',
    linkLabel: 'Bookings',
  },
  {
    key: 'payment_proofs_to_check',
    label: 'Payment Proof To Check',
    description: 'Payment submissions waiting for staff verification/review.',
    icon: 'Pv',
    iconClass: 'bg-sky-50 text-sky-600 border-sky-100',
    hash: 'bookings',
    linkLabel: 'Bookings',
  },
  {
    key: 'upcoming_events',
    label: 'Upcoming Events',
    description: 'Scheduled Carboot@CMart events that staff may need to monitor.',
    icon: 'Ev',
    iconClass: 'bg-emerald-50 text-emerald-600 border-emerald-100',
    hash: 'events',
    linkLabel: 'Events',
  },
  {
    key: 'feedback_to_review',
    label: 'Feedback To Review',
    description: 'Community feedback waiting for staff action.',
    icon: 'Fb',
    iconClass: 'bg-violet-50 text-violet-600 border-violet-100',
    hash: 'feedback',
    linkLabel: 'Feedback',
  },
];

const visibleCards = computed(() => {
  if (!summary.value) return [];

  return cardDefinitions
    .filter((card) => summary.value[card.key] != null)
    .map((card) => ({
      ...card,
      value: summary.value[card.key],
    }));
});

const load = async () => {
  loading.value = true;
  loadError.value = null;

  try {
    const { data } = await api.get('/organizer/operations-summary');
    summary.value = data;
    hasLoaded.value = true;
  } catch (error) {
    loadError.value = error.forbiddenMessage
      || error.response?.data?.message
      || 'Unable to load operational counts.';
    throw error;
  } finally {
    loading.value = false;
  }
};

defineExpose({
  refresh: load,
  hasLoaded,
});
</script>
