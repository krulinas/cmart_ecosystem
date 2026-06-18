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
        v-if="modelValue"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 p-6 max-h-[90vh] overflow-y-auto" @click.stop>
          <h2 :id="titleId" class="text-xl font-extrabold text-ink-900">
            {{ item ? 'Edit Reuse Item' : 'Add Reuse Item' }}
          </h2>
          <p class="mt-1 text-sm text-ink-500">List pre-loved goods customers can find at your booth.</p>

          <form class="mt-6 space-y-4" @submit.prevent="save">
            <div>
              <label class="ml-label">Item name</label>
              <input v-model="form.name" class="ml-input" required />
              <p v-if="errors.name" class="mt-1 text-xs text-rose-600">{{ errors.name }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="ml-label">Category</label>
                <select v-model="form.category" class="ml-input" required>
                  <option value="">Select category</option>
                  <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
                    {{ category }}
                  </option>
                </select>
                <p v-if="errors.category" class="mt-1 text-xs text-rose-600">{{ errors.category }}</p>
              </div>
              <div>
                <label class="ml-label">Condition</label>
                <select v-model="form.condition" class="ml-input" required>
                  <option v-for="condition in ITEM_CONDITIONS" :key="condition" :value="condition">
                    {{ condition }}
                  </option>
                </select>
                <p v-if="errors.condition" class="mt-1 text-xs text-rose-600">{{ errors.condition }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="ml-label">Pricing</label>
                <select v-model="form.pricing_type" class="ml-input" required>
                  <option v-for="option in ITEM_PRICING_TYPES" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </div>
              <div v-if="form.pricing_type === 'fixed'">
                <label class="ml-label">Price (RM)</label>
                <input v-model="form.price" type="number" min="0" step="0.01" class="ml-input" required />
                <p v-if="errors.price" class="mt-1 text-xs text-rose-600">{{ errors.price }}</p>
              </div>
            </div>

            <div>
              <label class="ml-label">Status</label>
              <select v-model="form.status" class="ml-input" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div>
              <label class="ml-label">Description</label>
              <textarea v-model="form.description" rows="3" class="ml-input" placeholder="Optional item details…"></textarea>
              <p v-if="errors.description" class="mt-1 text-xs text-rose-600">{{ errors.description }}</p>
            </div>

            <div>
              <label class="ml-label">Item images (optional, up to 5)</label>
              <input
                ref="imageInput"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                multiple
                class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
                :disabled="remainingImageSlots <= 0"
                @change="onImagesSelected"
              />
              <p class="text-xs text-ink-500 mt-1">
                JPG, PNG, or WEBP up to 5 MB each. {{ imageCountLabel }}.
              </p>

              <div v-if="visibleExistingImages.length" class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div
                  v-for="image in visibleExistingImages"
                  :key="`existing-${image.id}`"
                  class="relative overflow-hidden rounded-lg border border-ink-200 bg-ink-50"
                >
                  <img
                    :src="image.image_url"
                    :alt="`${form.name || 'Item'} image`"
                    class="h-24 w-full object-cover object-center"
                  />
                  <button
                    type="button"
                    class="absolute right-1 top-1 rounded-md bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-rose-700 shadow-sm"
                    @click="markExistingImageRemoved(image.id)"
                  >
                    Remove
                  </button>
                  <span
                    v-if="image.is_primary"
                    class="absolute bottom-1 left-1 rounded-md bg-cyan-600/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                  >
                    Primary
                  </span>
                </div>
              </div>

              <div v-if="newImagePreviews.length" class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div
                  v-for="preview in newImagePreviews"
                  :key="preview.key"
                  class="relative overflow-hidden rounded-lg border border-brand-200 bg-brand-50/40"
                >
                  <img :src="preview.url" alt="New item preview" class="h-24 w-full object-cover object-center" />
                  <button
                    type="button"
                    class="absolute right-1 top-1 rounded-md bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-rose-700 shadow-sm"
                    @click="removeNewImage(preview.key)"
                  >
                    Remove
                  </button>
                </div>
              </div>
            </div>

            <div class="flex gap-2 pt-2">
              <button type="submit" class="ml-btn-primary" :disabled="saving">
                {{ saving ? 'Saving…' : item ? 'Save Changes' : 'Add Item' }}
              </button>
              <button type="button" class="ml-btn-ghost" :disabled="saving" @click="close">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../services/api';
import { extractApiError } from '../utils/apiErrors';
import { PRODUCT_CATEGORIES } from '../utils/bookingDisplay';
import { resolveReuseItemGallery } from '../utils/imageUrl';
import { ITEM_CONDITIONS, ITEM_PRICING_TYPES } from '../utils/vendorCatalog';

const MAX_IMAGES = 5;

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const toast = useToast();
const titleId = computed(() => (props.item ? 'vendor-item-edit-title' : 'vendor-item-create-title'));

const saving = ref(false);
const errors = reactive({});
const imageInput = ref(null);
const existingImages = ref([]);
const removeImageIds = ref([]);
const newImageFiles = ref([]);
const newImagePreviews = ref([]);
let previewKeyCounter = 0;

const emptyForm = () => ({
  name: '',
  category: '',
  condition: 'Good',
  pricing_type: 'fixed',
  price: '',
  description: '',
  status: 'active',
});

const form = reactive(emptyForm());

const revokeNewPreviews = () => {
  for (const preview of newImagePreviews.value) {
    if (preview.url?.startsWith('blob:')) {
      URL.revokeObjectURL(preview.url);
    }
  }
};

const keptExistingCount = computed(() =>
  existingImages.value.filter((image) => !removeImageIds.value.includes(image.id)).length,
);

const remainingImageSlots = computed(() =>
  Math.max(0, MAX_IMAGES - keptExistingCount.value - newImageFiles.value.length),
);

const visibleExistingImages = computed(() =>
  existingImages.value.filter((image) => !removeImageIds.value.includes(image.id)),
);

const imageCountLabel = computed(() => {
  const total = keptExistingCount.value + newImageFiles.value.length;
  return `${total} of ${MAX_IMAGES} images selected`;
});

const clearErrors = () => {
  Object.keys(errors).forEach((key) => {
    delete errors[key];
  });
};

const applyValidationErrors = (error) => {
  clearErrors();
  const validationErrors = error?.response?.data?.errors;
  if (!validationErrors) return;
  Object.entries(validationErrors).forEach(([field, messages]) => {
    errors[field] = messages?.[0];
  });
};

const resetForm = () => {
  Object.assign(form, emptyForm());
  existingImages.value = [];
  removeImageIds.value = [];
  newImageFiles.value = [];
  revokeNewPreviews();
  newImagePreviews.value = [];
  if (imageInput.value) imageInput.value.value = '';
  clearErrors();
};

const fillForm = (item) => {
  form.name = item?.name || '';
  form.category = item?.category || '';
  form.condition = item?.condition || 'Good';
  form.pricing_type = item?.pricing_type || 'fixed';
  form.price = item?.price != null ? String(item.price) : '';
  form.description = item?.description || '';
  form.status = item?.status || 'active';
  existingImages.value = resolveReuseItemGallery(item).filter((image) => image.id != null);
};

watch(
  () => [props.modelValue, props.item],
  ([open, item]) => {
    if (!open) return;
    resetForm();
    if (item) fillForm(item);
  },
);

const close = () => emit('update:modelValue', false);

const onImagesSelected = (event) => {
  const files = Array.from(event.target.files || []);
  if (!files.length) return;

  const allowed = files.slice(0, remainingImageSlots.value);
  if (allowed.length < files.length) {
    toast.info(`Only ${MAX_IMAGES} images are allowed per item.`);
  }

  for (const file of allowed) {
    const key = `new-${++previewKeyCounter}`;
    newImageFiles.value.push(file);
    newImagePreviews.value.push({
      key,
      url: URL.createObjectURL(file),
    });
  }

  if (imageInput.value) imageInput.value.value = '';
};

const removeNewImage = (key) => {
  const previewIndex = newImagePreviews.value.findIndex((preview) => preview.key === key);
  if (previewIndex === -1) return;

  const [preview] = newImagePreviews.value.splice(previewIndex, 1);
  newImageFiles.value.splice(previewIndex, 1);

  if (preview?.url?.startsWith('blob:')) {
    URL.revokeObjectURL(preview.url);
  }
};

const markExistingImageRemoved = (imageId) => {
  if (!removeImageIds.value.includes(imageId)) {
    removeImageIds.value.push(imageId);
  }
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append('name', form.name.trim());
  fd.append('category', form.category);
  fd.append('condition', form.condition);
  fd.append('pricing_type', form.pricing_type);
  fd.append('status', form.status);
  fd.append('description', form.description?.trim() || '');
  if (form.pricing_type === 'fixed') {
    fd.append('price', form.price);
  }
  for (const file of newImageFiles.value) {
    fd.append('images[]', file);
  }
  for (const imageId of removeImageIds.value) {
    fd.append('remove_image_ids[]', String(imageId));
  }
  return fd;
};

const hasImageChanges = computed(() =>
  newImageFiles.value.length > 0 || removeImageIds.value.length > 0,
);

const save = async () => {
  saving.value = true;
  clearErrors();
  const usesMultipart = hasImageChanges.value;

  try {
    if (props.item) {
      if (usesMultipart) {
        const fd = buildFormData();
        fd.append('_method', 'PUT');
        await api.post(`/vendor/items/${props.item.id}`, fd);
      } else {
        await api.put(`/vendor/items/${props.item.id}`, {
          name: form.name.trim(),
          category: form.category,
          condition: form.condition,
          pricing_type: form.pricing_type,
          price: form.pricing_type === 'fixed' ? Number(form.price) : null,
          description: form.description?.trim() || null,
          status: form.status,
        });
      }
      toast.success('Reuse item updated.');
    } else if (usesMultipart || newImageFiles.value.length) {
      await api.post('/vendor/items', buildFormData());
      toast.success('Reuse item added.');
    } else {
      await api.post('/vendor/items', {
        name: form.name.trim(),
        category: form.category,
        condition: form.condition,
        pricing_type: form.pricing_type,
        price: form.pricing_type === 'fixed' ? Number(form.price) : null,
        description: form.description?.trim() || null,
        status: form.status,
      });
      toast.success('Reuse item added.');
    }

    emit('saved');
    close();
  } catch (error) {
    console.error('Unable to save vendor item:', error);
    applyValidationErrors(error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};
</script>
