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
              <label class="ml-label">Item image (optional)</label>
              <input
                ref="imageInput"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
                @change="onImageSelected"
              />
              <p class="text-xs text-ink-500 mt-1">JPG, PNG, or WEBP up to 5 MB.</p>
              <div v-if="imagePreviewUrl" class="mt-3">
                <img :src="imagePreviewUrl" alt="Item preview" class="max-h-40 rounded-lg border border-ink-200 object-cover" />
                <button type="button" class="ml-btn-ghost text-sm text-rose-600 mt-2" @click="clearImageSelection">
                  Remove selected image
                </button>
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
import { ITEM_CONDITIONS, ITEM_PRICING_TYPES } from '../utils/vendorCatalog';

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
const imageFile = ref(null);
const imagePreviewUrl = ref('');
const removeImage = ref(false);
let objectPreviewUrl = '';

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

const revokeObjectPreview = () => {
  if (objectPreviewUrl) {
    URL.revokeObjectURL(objectPreviewUrl);
    objectPreviewUrl = '';
  }
};

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
  imageFile.value = null;
  removeImage.value = false;
  revokeObjectPreview();
  imagePreviewUrl.value = '';
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
  imagePreviewUrl.value = item?.image_url || '';
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

const onImageSelected = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  imageFile.value = file;
  removeImage.value = false;
  revokeObjectPreview();
  objectPreviewUrl = URL.createObjectURL(file);
  imagePreviewUrl.value = objectPreviewUrl;
};

const clearImageSelection = () => {
  imageFile.value = null;
  removeImage.value = true;
  revokeObjectPreview();
  imagePreviewUrl.value = '';
  if (imageInput.value) imageInput.value.value = '';
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
  if (imageFile.value) {
    fd.append('image', imageFile.value);
  }
  if (removeImage.value) {
    fd.append('remove_image', '1');
  }
  return fd;
};

const save = async () => {
  saving.value = true;
  clearErrors();
  const usesMultipart = Boolean(imageFile.value || removeImage.value);

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
    } else if (usesMultipart) {
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
