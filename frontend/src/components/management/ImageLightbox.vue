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
        v-if="open && currentImageUrl"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="ariaLabel"
      >
        <div
          class="absolute inset-0 bg-black/80 backdrop-blur-sm"
          aria-hidden="true"
          @click="close"
        />

        <div class="relative z-10 flex max-h-[90vh] max-w-[90vw] flex-col items-center justify-center">
          <button
            type="button"
            class="absolute -top-12 right-0 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-700 shadow-lg ring-1 ring-white/20 transition hover:bg-white"
            aria-label="Close image preview"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <button
            v-if="hasMultipleImages"
            type="button"
            class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-700 shadow-lg ring-1 ring-white/20 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 sm:-translate-x-14"
            :disabled="currentIndex === 0"
            aria-label="Previous image"
            @click.stop="showPrevious"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <button
            v-if="hasMultipleImages"
            type="button"
            class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-700 shadow-lg ring-1 ring-white/20 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 sm:translate-x-14"
            :disabled="currentIndex >= resolvedImages.length - 1"
            aria-label="Next image"
            @click.stop="showNext"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <img
            :src="currentImageUrl"
            :alt="altText"
            class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
            @click.stop
          />

          <p v-if="caption" class="mt-3 text-center text-sm text-white/90">{{ caption }}</p>
          <p v-if="hasMultipleImages" class="mt-2 text-center text-xs font-semibold text-white/75">
            {{ currentIndex + 1 }} / {{ resolvedImages.length }}
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch, onUnmounted, computed, ref } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  imageUrl: { type: String, default: null },
  images: { type: Array, default: () => [] },
  startIndex: { type: Number, default: 0 },
  altText: { type: String, default: 'Image preview' },
  caption: { type: String, default: '' },
});

const emit = defineEmits(['update:open', 'close']);

const currentIndex = ref(0);

const resolvedImages = computed(() => {
  if (Array.isArray(props.images) && props.images.length) {
    return props.images.filter((url) => typeof url === 'string' && url.trim());
  }

  if (props.imageUrl) {
    return [props.imageUrl];
  }

  return [];
});

const currentImageUrl = computed(() => resolvedImages.value[currentIndex.value] ?? null);
const hasMultipleImages = computed(() => resolvedImages.value.length > 1);

const ariaLabel = computed(() => `Full size ${props.altText}`);

const syncCurrentIndex = () => {
  const maxIndex = Math.max(0, resolvedImages.value.length - 1);
  currentIndex.value = Math.min(Math.max(0, props.startIndex), maxIndex);
};

const close = () => {
  emit('update:open', false);
  emit('close');
};

const showPrevious = () => {
  if (currentIndex.value > 0) {
    currentIndex.value -= 1;
  }
};

const showNext = () => {
  if (currentIndex.value < resolvedImages.value.length - 1) {
    currentIndex.value += 1;
  }
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.open) {
    event.stopPropagation();
    close();
  }
};

const onArrowKeys = (event) => {
  if (!props.open || !hasMultipleImages.value) return;

  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    showPrevious();
  } else if (event.key === 'ArrowRight') {
    event.preventDefault();
    showNext();
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      syncCurrentIndex();
      document.addEventListener('keydown', onEscape, true);
      document.addEventListener('keydown', onArrowKeys, true);
    } else {
      document.removeEventListener('keydown', onEscape, true);
      document.removeEventListener('keydown', onArrowKeys, true);
    }
  },
);

watch(
  () => [props.startIndex, props.images, props.imageUrl],
  () => {
    if (props.open) {
      syncCurrentIndex();
    }
  },
);

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape, true);
  document.removeEventListener('keydown', onArrowKeys, true);
});
</script>
