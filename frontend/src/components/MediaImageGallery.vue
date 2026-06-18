<template>
  <div class="border-b border-ink-100 bg-ink-50/60">
    <div
      v-if="selectedImageUrl"
      class="flex h-64 sm:h-80 items-center justify-center bg-ink-50 px-4 py-3"
    >
      <img
        :src="selectedImageUrl"
        :alt="altText"
        class="max-h-full max-w-full object-contain"
        @error="onMainImageError"
      />
    </div>
    <div
      v-else
      class="flex h-48 items-center justify-center bg-ink-50 text-sm font-semibold uppercase tracking-wide text-ink-400"
    >
      {{ placeholderText }}
    </div>

    <div
      v-if="galleryImages.length > 1"
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
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  images: { type: Array, default: () => [] },
  altText: { type: String, default: 'Image' },
  placeholderText: { type: String, default: 'No image available' },
});

const selectedImage = ref(null);
const mainImageBroken = ref(false);
const brokenThumbnailKeys = ref(new Set());

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

  const images = galleryImages.value;
  if (!images.length) {
    selectedImage.value = null;
    return;
  }

  selectedImage.value = images.find((image) => image.is_primary) || images[0];
};

watch(() => props.images, syncSelection, { immediate: true, deep: true });
</script>
