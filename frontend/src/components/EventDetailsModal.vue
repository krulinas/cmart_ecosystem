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
              <div class="mb-4 lg:mb-0">
                <MediaImageGallery
                  :images="event.images || []"
                  :alt-text="`${event.title} event poster`"
                  placeholder-text="No event image"
                  enable-lightbox
                />
              </div>

              <div class="flex flex-col min-w-0" :class="event.posterUrl ? '' : 'lg:col-span-2'">
                <p class="text-xs font-bold uppercase tracking-wider text-brand-600 mb-2">Event Details</p>
                <h2 :id="titleId" class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight pr-10">
                  {{ event.title }}
                </h2>

                <dl class="mt-5 space-y-3 text-sm text-gray-700">
                  <div v-if="event.dateLabel" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">Date</dt>
                    <dd>{{ event.dateLabel }}</dd>
                  </div>
                  <div v-if="event.time" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">Time</dt>
                    <dd>{{ event.time }}</dd>
                  </div>
                  <div v-if="event.location" class="flex gap-3">
                    <dt class="w-20 shrink-0 font-semibold text-gray-500">Location</dt>
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

                <p v-if="event.description" class="mt-5 text-sm sm:text-base text-gray-600 leading-relaxed whitespace-pre-line">
                  {{ event.description }}
                </p>

                <div class="mt-8 pt-5 border-t border-gray-100 flex flex-wrap gap-3">
                  <router-link
                    :to="bookingLink"
                    class="inline-flex items-center justify-center rounded-full bg-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700"
                    @click="close"
                  >
                    {{ bookingLabel }}
                  </router-link>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
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

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  event: { type: Object, default: null },
  bookingLink: { type: String, required: true },
  bookingLabel: { type: String, default: 'Book Space' },
});

const emit = defineEmits(['update:modelValue']);

const panelRef = ref(null);

const titleId = computed(() => (props.event?.id ? `event-modal-title-${props.event.id}` : 'event-modal-title'));

const close = () => {
  emit('update:modelValue', false);
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

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onEscape);
});
</script>
