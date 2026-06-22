<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue && event"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc="close"
      >
        <div
          class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]"
          aria-hidden="true"
          @click="close"
        />

        <Transition
          appear
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="modelValue"
            ref="panelRef"
            class="relative z-10 w-full max-w-5xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
            tabindex="-1"
            @click.stop
          >
            <button
              type="button"
              class="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-500 shadow-md ring-1 ring-gray-200 transition hover:bg-gray-50 hover:text-gray-800"
              aria-label="Close event details"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-6 p-5 sm:p-6 lg:p-8">
              <div ref="gallerySectionRef" class="mb-4 lg:mb-0">
                <MediaImageGallery
                  ref="galleryRef"
                  :images="event.images || []"
                  :alt-text="`${event.title} event poster`"
                  placeholder-text="No event poster yet"
                  enable-lightbox
                />
              </div>

              <div class="flex flex-col min-w-0" :class="hasPoster ? '' : 'lg:col-span-2'">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                  <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Event Details</p>
                  <span
                    v-if="urgencyLabel"
                    class="rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-800"
                  >
                    {{ urgencyLabel }}
                  </span>
                </div>

                <h2 :id="titleId" class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight pr-10">
                  {{ event.title }}
                </h2>

                <dl class="mt-5 space-y-3 text-sm text-gray-700">
                  <div v-if="event.dateLabel" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">
                      <span class="sr-only">Date</span>
                      <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </dt>
                    <dd class="font-medium">
                      <span>{{ event.dateLabel }}</span>
                      <span v-if="event.dateNumeric" class="mt-0.5 block text-xs font-normal text-gray-500">
                        {{ event.dateNumeric }}
                      </span>
                    </dd>
                  </div>
                  <div v-if="event.time" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">
                      <span class="sr-only">Time</span>
                      <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </dt>
                    <dd class="font-medium">{{ event.time }}</dd>
                  </div>
                  <div v-if="event.location" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">
                      <span class="sr-only">Location</span>
                      <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                    </dt>
                    <dd>{{ event.location }}</dd>
                  </div>
                  <div v-if="event.status" class="flex gap-3 items-center">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">Status</dt>
                    <dd>
                      <span :class="['text-xs font-bold px-3 py-1 rounded-full', event.statusClass]">
                        {{ event.status }}
                      </span>
                    </dd>
                  </div>
                </dl>

                <div v-if="showDescription" class="mt-5">
                  <p
                    class="text-sm sm:text-base text-gray-600 leading-relaxed whitespace-pre-line"
                    :class="descriptionExpanded ? '' : 'line-clamp-4'"
                  >
                    {{ event.description }}
                  </p>
                  <button
                    v-if="descriptionIsLong"
                    type="button"
                    class="mt-2 text-sm font-semibold text-brand-600 hover:text-brand-700"
                    @click="descriptionExpanded = !descriptionExpanded"
                  >
                    {{ descriptionExpanded ? 'Show less' : 'View full details' }}
                  </button>
                </div>

                <div class="mt-8 pt-5 border-t border-gray-100 flex flex-wrap gap-3">
                  <router-link
                    v-if="showBookingCta"
                    :to="bookingLink"
                    class="inline-flex items-center justify-center rounded-full bg-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                    @click="close"
                  >
                    {{ bookingLabel }}
                  </router-link>
                  <button
                    v-if="hasPoster"
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-brand-200 bg-brand-50 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                    @click="viewPoster"
                  >
                    View Poster
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    @click="addToCalendar"
                  >
                    Add to Calendar
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    @click="close"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onUnmounted, nextTick } from 'vue';
import MediaImageGallery from './MediaImageGallery.vue';
import {
  downloadEventIcs,
  getEventUrgencyLabel,
  isEventBookable,
} from '../utils/eventDisplay';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  event: { type: Object, default: null },
  bookingLink: { type: String, required: true },
  bookingLabel: { type: String, default: 'Book Space' },
  hideBookingWhenClosed: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const panelRef = ref(null);
const galleryRef = ref(null);
const gallerySectionRef = ref(null);
const descriptionExpanded = ref(false);

const titleId = computed(() => (props.event?.id ? `event-modal-title-${props.event.id}` : 'event-modal-title'));

const hasPoster = computed(() => Boolean(props.event?.posterUrl || props.event?.images?.length));

const urgencyLabel = computed(() => {
  if (!props.event) return '';
  return getEventUrgencyLabel(props.event.startsAt, props.event.endsAt);
});

const showBookingCta = computed(() => {
  if (!props.event) return false;
  if (!props.hideBookingWhenClosed) return true;
  return isEventBookable(props.event.status) && urgencyLabel.value !== 'Event ended';
});

const defaultDescription = 'Join us for a weekend of bargains, local vendors, and community fun.';

const showDescription = computed(() => {
  const text = props.event?.description;
  return text && text !== defaultDescription;
});

const descriptionIsLong = computed(() => (props.event?.description?.length || 0) > 220);

const close = () => {
  emit('update:modelValue', false);
};

const viewPoster = () => {
  if (galleryRef.value?.openImagePreview) {
    galleryRef.value.openImagePreview();
    return;
  }
  gallerySectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

const addToCalendar = () => {
  if (props.event) downloadEventIcs(props.event);
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.modelValue) {
    close();
  }
};

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      descriptionExpanded.value = false;
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onEscape);
      await nextTick();
      panelRef.value?.focus();
    } else {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onEscape);
    }
  },
);

watch(
  () => props.event?.id,
  () => {
    descriptionExpanded.value = false;
  },
);

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onEscape);
});
</script>
