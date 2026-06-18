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
        v-for="image in existingImages"
        :key="`existing-${image.id}`"
        class="relative rounded-lg border border-ink-200 overflow-hidden"
      >
        <img :src="image.image_url" :alt="`${label} existing`" class="h-28 w-full object-cover" />
        <button
          type="button"
          class="absolute top-1 right-1 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
          @click="removeExisting(image.id)"
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
        v-for="preview in newPreviews"
        :key="preview.key"
        class="relative rounded-lg border border-ink-200 overflow-hidden"
      >
        <img :src="preview.url" :alt="`${label} new preview`" class="h-28 w-full object-cover" />
        <button
          type="button"
          class="absolute top-1 right-1 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
          @click="removeNewPreview(preview.key)"
        >
          Remove
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { resolveStorageUrl } from '../utils/imageUrl';

const props = defineProps({
  label: { type: String, default: 'Images (optional)' },
  maxImages: { type: Number, default: 5 },
  existing: { type: Array, default: () => [] },
  legacyField: { type: String, default: '' },
});

const emit = defineEmits(['update:files', 'update:removeIds']);

const fileInput = ref(null);
const newFiles = ref([]);
const newPreviews = ref([]);
const removedIds = ref(new Set());

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
