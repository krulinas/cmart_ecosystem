<template>
  <div :class="compact ? 'h-[140px] overflow-hidden bg-gray-100' : 'border-b border-ink-100 bg-ink-50/60'">
    <div
      v-if="selectedImageUrl"
      :class="compact
        ? 'h-full w-full'
        : 'flex h-64 sm:h-80 items-center justify-center bg-ink-50 px-4 py-3'"
    >
      <button
        v-if="enableLightbox"
        type="button"
        :class="compact
          ? 'group relative block h-full w-full cursor-pointer overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-inset'
          : 'group relative flex h-full w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2'"
        :aria-label="`View full ${altText}`"
        @click="openImagePreview"
      >
        <img
          :src="selectedImageUrl"
          :alt="altText"
          :class="compact
            ? 'h-full w-full object-cover object-top transition duration-300 group-hover:scale-[1.03] group-hover:opacity-95'
            : 'max-h-full max-w-full object-contain transition duration-300 group-hover:scale-[1.02] group-hover:opacity-95'"
          @error="onMainImageError"
        />
        <span
          class="pointer-events-none absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
          aria-hidden="true"
        >
          <span
            class="rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
            :class="compact ? 'mb-2' : 'mb-3'"
          >
            Click to view
          </span>
        </span>
      </button>
      <img
        v-else
        :src="selectedImageUrl"
        :alt="altText"
        class="max-h-full max-w-full object-contain"
        @error="onMainImageError"
      />
    </div>
    <div
      v-else
      :class="compact
        ? 'flex h-full items-center justify-center bg-gradient-to-br from-brand-100 to-brand-50 text-brand-400'
        : 'flex h-48 items-center justify-center bg-ink-50 text-sm font-semibold uppercase tracking-wide text-ink-400'"
    >
      <span v-if="compact" class="font-black text-4xl">@</span>
      <template v-else>{{ placeholderText }}</template>
    </div>

    <div
      v-if="!compact && galleryImages.length > 1"
      class="flex gap-2 overflow-x-auto border-t border-ink-100 bg-white px-4 py-3"
    >
      <button
        v-for="image in galleryImages"
        :key="imageKey(image)"
        type="button"
        class="shrink-0 overflow-hidden rounded-lg border-2 transition"
        :class="isSelected(image) ? 'border-cyan-500 ring-2 ring-cyan-200/70' : 'border-ink-200 hover:border-cyan-300'"
        :aria-label="`View image ${(image.sort_order ?? 0) + 1}`"
        @click="selectImage(image)"
      >
        <img
          :src="image.image_url"
          :alt="`${altText} thumbnail`"
          class="h-14 w-14 object-cover object-center bg-ink-100"
          @error="onThumbnailError(image)"
        />
      </button>
    </div>

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
          v-if="enableLightbox && isImagePreviewOpen && selectedImageUrl"
          class="fixed inset-0 z-[110] flex items-center justify-center p-4"
          role="dialog"
          aria-modal="true"
          :aria-label="`Full size ${altText}`"
        >
          <div
            class="absolute inset-0 bg-black/80 backdrop-blur-sm"
            aria-hidden="true"
            @click="closeImagePreview"
          />

          <div class="relative z-10 flex max-h-[90vh] max-w-[90vw] items-center justify-center">
            <button
              type="button"
              class="absolute -top-12 right-0 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-700 shadow-lg ring-1 ring-white/20 transition hover:bg-white"
              aria-label="Close image preview"
              @click="closeImagePreview"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <img
              :src="selectedImageUrl"
              :alt="altText"
              class="max-h-[90vh] max-w-[90vw] object-contain rounded-lg shadow-2xl"
              @click.stop
            />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';

const props = defineProps({
  images: { type: Array, default: () => [] },
  altText: { type: String, default: 'Image' },
  placeholderText: { type: String, default: 'No image available' },
  enableLightbox: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

const selectedImage = ref(null);
const mainImageBroken = ref(false);
const brokenThumbnailKeys = ref(new Set());
const isImagePreviewOpen = ref(false);

const galleryImages = computed(() =>
  (Array.isArray(props.images) ? props.images : [])
    .filter((image) => image?.image_url)
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
);

const selectedImageUrl = computed(() => {
  if (mainImageBroken.value || !selectedImage.value) return null;
  return selectedImage.value.image_url;
});

const imageKey = (image) => image.id ?? `legacy-${image.sort_order ?? 0}`;

const isSelected = (image) => imageKey(image) === imageKey(selectedImage.value || {});

const selectImage = (image) => {
  if (brokenThumbnailKeys.value.has(imageKey(image))) return;
  selectedImage.value = image;
  mainImageBroken.value = false;
};

const openImagePreview = () => {
  if (!selectedImageUrl.value) return;
  isImagePreviewOpen.value = true;
  document.addEventListener('keydown', onLightboxEscape, true);
};

const closeImagePreview = () => {
  isImagePreviewOpen.value = false;
  document.removeEventListener('keydown', onLightboxEscape, true);
};

const onLightboxEscape = (event) => {
  if (event.key === 'Escape' && isImagePreviewOpen.value) {
    event.stopPropagation();
    closeImagePreview();
  }
};

const onMainImageError = () => {
  mainImageBroken.value = true;
};

const onThumbnailError = (image) => {
  brokenThumbnailKeys.value = new Set([...brokenThumbnailKeys.value, imageKey(image)]);
  if (isSelected(image)) {
    mainImageBroken.value = true;
  }
};

const syncSelection = () => {
  mainImageBroken.value = false;
  brokenThumbnailKeys.value = new Set();
  closeImagePreview();

  const images = galleryImages.value;
  if (!images.length) {
    selectedImage.value = null;
    return;
  }

  selectedImage.value = images.find((image) => image.is_primary) || images[0];
};

watch(() => props.images, syncSelection, { immediate: true, deep: true });

onUnmounted(() => {
  document.removeEventListener('keydown', onLightboxEscape, true);
});

defineExpose({ openImagePreview });
</script>
