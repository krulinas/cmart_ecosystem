<template>
  <article
    data-testid="public-event-card"
    tabindex="0"
    role="button"
    :aria-label="`View details for ${event.title}`"
    class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:-translate-y-1 hover:border-brand-200 hover:ring-2 hover:ring-brand-500/15 transition-all duration-300 ease-out relative overflow-hidden group flex flex-col h-full cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
    @click="$emit('select', event)"
    @keydown.enter.prevent="$emit('select', event)"
    @keydown.space.prevent="$emit('select', event)"
  >
    <div class="absolute top-0 left-0 w-full h-1 bg-brand-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>

    <div
      v-if="event.posterUrl"
      class="w-full h-[140px] rounded-xl mb-4 border border-gray-100 overflow-hidden pointer-events-none"
    >
      <img
        :src="event.posterUrl"
        :alt="`${event.title} poster preview`"
        class="h-full w-full object-cover object-top"
      />
    </div>
    <div
      v-else
      class="pointer-events-none mb-4 flex h-[140px] w-full flex-col items-center justify-center rounded-xl border border-brand-100 bg-gradient-to-br from-brand-50 via-sky-50 to-cyan-50"
      aria-hidden="true"
    >
      <span class="text-xs font-bold uppercase tracking-[0.2em] text-brand-500">CMart Carboot</span>
      <span class="mt-2 text-3xl font-black text-brand-300/80">@</span>
    </div>

    <div class="flex items-start space-x-4 mb-4 pointer-events-none">
      <div class="bg-brand-50 text-brand-600 rounded-xl p-3 text-center min-w-[70px] border border-brand-100 group-hover:bg-brand-500 group-hover:text-white transition-colors duration-300">
        <span class="block text-3xl font-black leading-none mb-1">{{ event.day }}</span>
        <span class="block text-xs uppercase font-bold tracking-widest">{{ event.month }}</span>
      </div>
      <div class="pt-1 min-w-0">
        <h3 class="text-xl font-bold text-gray-900 mb-1 line-clamp-2">{{ event.title }}</h3>
        <p class="text-sm text-gray-500 flex items-center font-medium mb-1">
          <svg class="w-4 h-4 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ event.time }}
        </p>
        <p v-if="event.location" class="text-sm text-gray-500 flex items-start font-medium">
          <svg class="w-4 h-4 mr-1 mt-0.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="line-clamp-2">{{ event.location }}</span>
        </p>
      </div>
    </div>

    <p v-if="event.description" class="text-base text-gray-600 leading-relaxed mb-5 line-clamp-3 flex-grow pointer-events-none">
      {{ event.description }}
    </p>

    <p class="text-xs text-brand-600 font-semibold mb-3 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
      Click to view full poster and details
    </p>

    <div class="flex justify-between items-center mt-auto pt-5 border-t border-gray-100/80" @click.stop>
      <span :class="['text-xs font-bold px-4 py-1.5 rounded-full pointer-events-none', event.statusClass]">{{ event.status }}</span>
      <router-link
        :to="bookingLink"
        class="inline-flex items-center text-sm font-bold text-brand-600 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-4 py-1.5 rounded-full transition-colors"
        @click.stop
      >
        Book Space <span class="ml-1">→</span>
      </router-link>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { vendorBookingLink } from '../../utils/vendorBooking';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
  event: { type: Object, required: true },
});

defineEmits(['select']);

const auth = useAuthStore();
const bookingLink = computed(() => vendorBookingLink(props.event?.id, auth));
</script>
