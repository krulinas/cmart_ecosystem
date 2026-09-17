<template>
  <div>
    <label class="ml-label">{{ label }}</label>
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/jpg,image/png,image/webp"
      multiple
      class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
      :disabled="!canAddMore"
      @change="onFilesSelected"
    />
    <p class="text-xs text-ink-500 mt-1">
      JPG, JPEG, PNG, or WEBP up to 5 MB each. Maximum {{ maxImages }} images.
      <span v-if="!canAddMore" class="text-amber-700 font-semibold">Limit reached.</span>
    </p>

    <div v-if="existingImages.length || newPreviews.length" class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div
        v-for="(image, index) in existingImages"
        :key="`existing-${image.id}`"
        class="relative rounded-lg border border-ink-200 overflow-hidden"
      >
        <button
          v-if="enablePreview"
          type="button"
          class="block w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          :aria-label="`Preview ${label} image ${index + 1}`"
          @click="openPreview('existing', index)"
          @keydown.enter.prevent="openPreview('existing', index)"
          @keydown.space.prevent="openPreview('existing', index)"
        >
          <img
            :src="image.image_url"
            :alt="`${label} image ${index + 1}`"
            class="h-28 w-full object-cover"
          />
        </button>
        <img
          v-else
          :src="image.image_url"
          :alt="`${label} existing`"
          class="h-28 w-full object-cover"
        />
        <button
          type="button"
          class="absolute top-1 right-1 z-10 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
          @click.stop="removeExisting(image.id)"
        >
          Remove
        </button>
        <span
          v-if="image.is_primary"
          class="absolute bottom-1 left-1 rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
        >
          Primary
        </span>
      </div>

      <div
        v-for="(preview, index) in newPreviews"
        :key="preview.key"
        class="relative rounded-lg border border-ink-200 overflow-hidden"
      >
        <button
          v-if="enablePreview"
          type="button"
          class="block w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          :aria-label="`Preview newly selected ${label} image ${index + 1}`"
          @click="openPreview('new', index)"
          @keydown.enter.prevent="openPreview('new', index)"
          @keydown.space.prevent="openPreview('new', index)"
        >
          <img
            :src="preview.url"
            :alt="`Newly selected ${label} image ${index + 1}`"
            class="h-28 w-full object-cover"
          />
        </button>
        <img
          v-else
          :src="preview.url"
          :alt="`${label} new preview`"
          class="h-28 w-full object-cover"
        />
        <button
          type="button"
          class="absolute top-1 right-1 z-10 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
          @click.stop="removeNewPreview(preview.key)"
        >
          Remove
        </button>
      </div>
    </div>

    <ImageLightbox
      v-if="enablePreview"
      v-model:open="lightboxOpen"
      :images="lightboxImages"
      :start-index="lightboxStartIndex"
      :alt-text="lightboxAlt"
    />
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import ImageLightbox from './management/ImageLightbox.vue';
import { resolveStorageUrl } from '../utils/imageUrl';

const props = defineProps({
  label: { type: String, default: 'Images (optional)' },
  maxImages: { type: Number, default: 5 },
  existing: { type: Array, default: () => [] },
  legacyField: { type: String, default: '' },
  /** Opt-in full-size preview; off by default so other upload fields stay unchanged. */
  enablePreview: { type: Boolean, default: false },
});

const emit = defineEmits(['update:files', 'update:removeIds']);

const fileInput = ref(null);
const newFiles = ref([]);
const newPreviews = ref([]);
const removedIds = ref(new Set());
const lightboxOpen = ref(false);
const lightboxImages = ref([]);
const lightboxStartIndex = ref(0);
const lightboxAlt = ref('Image preview');

const existingImages = computed(() => {
  const fromGallery = (Array.isArray(props.existing) ? props.existing : [])
    .filter((image) => image?.id && !removedIds.value.has(image.id))
    .map((image) => ({
      ...image,
      image_url: resolveStorageUrl(image.image_url || image.image_path),
    }));

  if (fromGallery.length) {
    return fromGallery;
  }

  if (props.legacyField && !removedIds.value.has('legacy')) {
    const url = resolveStorageUrl(props.legacyField);
    if (url) {
      return [{
        id: 'legacy',
        image_url: url,
        is_primary: true,
      }];
    }
  }

  return [];
});

const totalCount = computed(() => existingImages.value.length + newFiles.value.length);
const canAddMore = computed(() => totalCount.value < props.maxImages);

const revokePreviews = () => {
  newPreviews.value.forEach((preview) => {
    if (preview.url?.startsWith('blob:')) {
      URL.revokeObjectURL(preview.url);
    }
  });
};

const syncEmit = () => {
  emit('update:files', [...newFiles.value]);
  emit('update:removeIds', [...removedIds.value].filter((id) => id !== 'legacy'));
};

const openPreview = (source, index) => {
  if (!props.enablePreview) return;

  const existingUrls = existingImages.value
    .map((image) => image.image_url)
    .filter(Boolean);
  const newUrls = newPreviews.value
    .map((preview) => preview.url)
    .filter(Boolean);
  const urls = [...existingUrls, ...newUrls];
  if (!urls.length) return;

  const startIndex = source === 'new'
    ? existingUrls.length + index
    : index;

  lightboxImages.value = urls;
  lightboxStartIndex.value = Math.min(Math.max(0, startIndex), urls.length - 1);
  lightboxAlt.value = props.label || 'Event image';
  lightboxOpen.value = true;
};

const onFilesSelected = (event) => {
  const files = Array.from(event.target.files || []);
  if (!files.length) return;

  const slotsLeft = props.maxImages - totalCount.value;
  const accepted = files.slice(0, Math.max(0, slotsLeft));

  accepted.forEach((file) => {
    const key = `${file.name}-${file.size}-${Date.now()}-${Math.random()}`;
    newFiles.value.push(file);
    newPreviews.value.push({ key, url: URL.createObjectURL(file) });
  });

  if (fileInput.value) {
    fileInput.value.value = '';
  }

  syncEmit();
};

const removeExisting = (id) => {
  removedIds.value = new Set([...removedIds.value, id]);
  syncEmit();
};

const removeNewPreview = (key) => {
  const index = newPreviews.value.findIndex((preview) => preview.key === key);
  if (index === -1) return;

  const preview = newPreviews.value[index];
  if (preview.url?.startsWith('blob:')) {
    URL.revokeObjectURL(preview.url);
  }

  newPreviews.value.splice(index, 1);
  newFiles.value.splice(index, 1);
  syncEmit();
};

const reset = () => {
  lightboxOpen.value = false;
  revokePreviews();
  newFiles.value = [];
  newPreviews.value = [];
  removedIds.value = new Set();
  if (fileInput.value) fileInput.value.value = '';
  syncEmit();
};

watch(() => props.existing, () => {
  if (!props.existing?.length) return;
  removedIds.value = new Set();
  syncEmit();
});

onUnmounted(revokePreviews);

defineExpose({ reset, hasLegacyRemoval: () => removedIds.value.has('legacy') });
</script>
