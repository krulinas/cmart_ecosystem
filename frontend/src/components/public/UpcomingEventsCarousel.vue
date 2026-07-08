<template>
  <div
    class="relative"
    data-testid="upcoming-events-carousel"
    @mouseenter="pauseAutoSlide"
    @mouseleave="resumeAutoSlide"
    @focusin="pauseAutoSlide"
    @focusout="onFocusOut"
  >
    <div class="overflow-hidden">
      <div
        class="flex transition-transform duration-500 ease-out motion-reduce:transition-none"
        :style="trackStyle"
      >
        <div
          v-for="event in events"
          :key="event.id"
          class="shrink-0 px-3 box-border"
          :style="slideStyle"
        >
          <PublicEventCard :event="event" @select="$emit('select', $event)" />
        </div>
      </div>
    </div>

    <template v-if="showControls">
      <button
        type="button"
        class="absolute left-0 top-1/2 z-10 -translate-y-1/2 -translate-x-1 sm:-translate-x-3 flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-md transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
        aria-label="Previous events"
        @click="goPrevious"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <button
        type="button"
        class="absolute right-0 top-1/2 z-10 -translate-y-1/2 translate-x-1 sm:translate-x-3 flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-md transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
        aria-label="Next events"
        @click="goNext"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </template>

    <div
      v-if="pageCount > 1"
      class="mt-6 flex justify-center gap-2"
      role="tablist"
      aria-label="Event carousel pages"
    >
      <button
        v-for="page in pageCount"
        :key="page"
        type="button"
        role="tab"
        class="h-2.5 rounded-full transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
        :class="page - 1 === currentPage ? 'w-8 bg-brand-600' : 'w-2.5 bg-gray-300 hover:bg-brand-300'"
        :aria-label="`Go to event slide ${page}`"
        :aria-selected="page - 1 === currentPage"
        @click="goToPage(page - 1)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import PublicEventCard from './PublicEventCard.vue';

const props = defineProps({
  events: { type: Array, default: () => [] },
  autoSlideMs: { type: Number, default: 4000 },
});

defineEmits(['select']);

const currentPage = ref(0);
const slidesPerView = ref(1);
const autoSlideEnabled = ref(true);
let autoTimer = null;
let reducedMotionQuery = null;

const updateSlidesPerView = () => {
  if (typeof window === 'undefined') return;
  if (window.matchMedia('(min-width: 1024px)').matches) {
    slidesPerView.value = 3;
  } else if (window.matchMedia('(min-width: 768px)').matches) {
    slidesPerView.value = 2;
  } else {
    slidesPerView.value = 1;
  }
};

const pageCount = computed(() => {
  const total = props.events.length;
  const perView = slidesPerView.value;
  if (total <= perView) return 1;
  return total - perView + 1;
});

const showControls = computed(() => props.events.length > slidesPerView.value);

const slideStyle = computed(() => ({
  width: `${100 / Math.max(props.events.length, 1)}%`,
}));

const trackStyle = computed(() => ({
  width: `${(props.events.length / slidesPerView.value) * 100}%`,
  transform: `translateX(-${(currentPage.value / props.events.length) * 100}%)`,
}));

const prefersReducedMotion = () =>
  typeof window !== 'undefined'
  && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const clearAutoTimer = () => {
  if (autoTimer) {
    clearInterval(autoTimer);
    autoTimer = null;
  }
};

const startAutoSlide = () => {
  clearAutoTimer();
  if (!autoSlideEnabled.value || prefersReducedMotion() || pageCount.value <= 1) return;

  autoTimer = setInterval(() => {
    currentPage.value = (currentPage.value + 1) % pageCount.value;
  }, props.autoSlideMs);
};

const pauseAutoSlide = () => {
  autoSlideEnabled.value = false;
  clearAutoTimer();
};

const resumeAutoSlide = () => {
  autoSlideEnabled.value = true;
  startAutoSlide();
};

const onFocusOut = (event) => {
  if (!event.currentTarget.contains(event.relatedTarget)) {
    resumeAutoSlide();
  }
};

const goToPage = (page) => {
  currentPage.value = Math.min(Math.max(0, page), pageCount.value - 1);
  if (autoSlideEnabled.value) {
    startAutoSlide();
  }
};

const goNext = () => {
  goToPage((currentPage.value + 1) % pageCount.value);
};

const goPrevious = () => {
  goToPage(currentPage.value === 0 ? pageCount.value - 1 : currentPage.value - 1);
};

const onReducedMotionChange = () => {
  if (prefersReducedMotion()) {
    clearAutoTimer();
  } else {
    startAutoSlide();
  }
};

watch([() => props.events.length, slidesPerView], () => {
  if (currentPage.value > pageCount.value - 1) {
    currentPage.value = Math.max(0, pageCount.value - 1);
  }
  startAutoSlide();
});

onMounted(() => {
  updateSlidesPerView();
  window.addEventListener('resize', updateSlidesPerView);
  reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  reducedMotionQuery.addEventListener('change', onReducedMotionChange);
  startAutoSlide();
});

onUnmounted(() => {
  clearAutoTimer();
  window.removeEventListener('resize', updateSlidesPerView);
  reducedMotionQuery?.removeEventListener('change', onReducedMotionChange);
});
</script>
