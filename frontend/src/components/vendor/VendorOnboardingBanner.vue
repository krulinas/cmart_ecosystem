<template>
  <section
    v-if="copy"
    class="rounded-2xl border p-5 sm:p-6"
    :class="toneClasses"
    data-testid="vendor-onboarding-banner"
  >
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <h2 class="text-lg font-extrabold" :class="titleClass">{{ copy.title }}</h2>
        <p class="mt-2 text-sm leading-relaxed" :class="messageClass">{{ copy.message }}</p>
      </div>
      <div v-if="showActions" class="flex flex-wrap gap-2 shrink-0">
        <router-link
          v-if="state === 'welcome' || state === 'rejected'"
          to="/vendor-booking"
          class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-brand-700 shadow-sm hover:bg-brand-50 transition"
        >
          Start Vendor Booking
        </router-link>
        <router-link
          v-if="state === 'needs_revision'"
          to="/dashboard"
          class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-amber-800 shadow-sm hover:bg-amber-50 transition"
          @click.prevent="$emit('review-booking')"
        >
          Review Booking
        </router-link>
        <router-link
          to="/community"
          class="inline-flex items-center justify-center rounded-xl border border-white/60 bg-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/30 transition"
        >
          Back to Community
        </router-link>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { VENDOR_ONBOARDING_COPY } from '../../utils/vendorOnboarding';

const props = defineProps({
  state: {
    type: String,
    required: true,
  },
  showActions: {
    type: Boolean,
    default: true,
  },
});

defineEmits(['review-booking']);

const copy = computed(() => VENDOR_ONBOARDING_COPY[props.state] ?? null);

const toneClasses = computed(() => {
  const tone = copy.value?.tone ?? 'brand';
  return {
    brand: 'border-brand-200 bg-gradient-to-br from-brand-50 to-white',
    info: 'border-sky-200 bg-gradient-to-br from-sky-50 to-white',
    warning: 'border-amber-200 bg-gradient-to-br from-amber-50 to-white',
    neutral: 'border-ink-200 bg-gradient-to-br from-ink-50 to-white',
    success: 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white',
  }[tone];
});

const titleClass = computed(() => {
  const tone = copy.value?.tone ?? 'brand';
  return {
    brand: 'text-brand-900',
    info: 'text-sky-900',
    warning: 'text-amber-900',
    neutral: 'text-ink-900',
    success: 'text-emerald-900',
  }[tone];
});

const messageClass = computed(() => {
  const tone = copy.value?.tone ?? 'brand';
  return {
    brand: 'text-brand-800/90',
    info: 'text-sky-800/90',
    warning: 'text-amber-800/90',
    neutral: 'text-ink-600',
    success: 'text-emerald-800/90',
  }[tone];
});
</script>
