<template>
  <aside id="vendor-business-profile" class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="text-xl font-extrabold text-ink-900">Business Profile</h2>
        <p class="text-sm text-ink-500 mt-1">Your vendor storefront details on file.</p>
      </div>
      <button
        v-if="!editing && !loading"
        type="button"
        class="ml-btn-ghost text-sm shrink-0"
        @click="startEditing"
      >
        Edit
      </button>
    </div>

    <div v-if="loading" class="mt-6 space-y-4 animate-pulse">
      <div class="h-20 w-20 rounded-2xl bg-ink-100"></div>
      <div class="h-4 w-3/4 rounded bg-ink-100"></div>
      <div class="h-4 w-1/2 rounded bg-ink-100"></div>
      <div class="h-16 rounded-xl bg-ink-100"></div>
    </div>

    <div v-else-if="loadError" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/70 p-6 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load business profile.</p>
      <button type="button" class="mt-3 ml-btn-ghost text-sm" @click="loadProfile">Try Again</button>
    </div>

    <form v-else-if="editing" class="mt-6 space-y-4" @submit.prevent="saveProfile">
      <div class="flex flex-col sm:flex-row gap-4 items-start">
        <div class="shrink-0">
          <div class="h-20 w-20 rounded-2xl border border-ink-200 bg-ink-50 overflow-hidden flex items-center justify-center">
            <img
              v-if="logoPreviewUrl"
              :src="logoPreviewUrl"
              alt="Business logo preview"
              class="h-full w-full object-cover"
            />
            <span v-else class="text-xs font-bold text-ink-400 text-center px-2">No logo</span>
          </div>
          <input
            ref="logoInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="hidden"
            @change="onLogoSelected"
          />
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" class="ml-btn-ghost text-xs" @click="logoInput?.click()">Upload logo</button>
            <button
              v-if="logoPreviewUrl"
              type="button"
              class="ml-btn-ghost text-xs text-rose-600"
              @click="clearLogoSelection"
            >
              Remove
            </button>
          </div>
        </div>
        <div class="flex-1 w-full space-y-4">
          <div>
            <label class="ml-label">Business name</label>
            <input v-model="form.business_name" class="ml-input" required />
            <p v-if="errors.business_name" class="mt-1 text-xs text-rose-600">{{ errors.business_name }}</p>
          </div>
          <div>
            <label class="ml-label">Business phone</label>
            <input v-model="form.business_phone" class="ml-input" />
            <p v-if="errors.business_phone" class="mt-1 text-xs text-rose-600">{{ errors.business_phone }}</p>
          </div>
        </div>
      </div>

      <div>
        <label class="ml-label">Business category</label>
        <select v-model="form.business_category" class="ml-input">
          <option value="">Select category</option>
          <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
            {{ category }}
          </option>
        </select>
        <p v-if="errors.business_category" class="mt-1 text-xs text-rose-600">{{ errors.business_category }}</p>
      </div>

      <div>
        <label class="ml-label">Business description</label>
        <textarea v-model="form.description" rows="4" class="ml-input" placeholder="Tell customers about your booth…"></textarea>
        <p v-if="errors.description" class="mt-1 text-xs text-rose-600">{{ errors.description }}</p>
      </div>

      <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-3 text-sm text-ink-600">
        Account email: <span class="font-semibold text-ink-900">{{ account.email || '—' }}</span>
      </div>

      <div class="flex flex-wrap gap-2 pt-1">
        <button type="submit" class="ml-btn-primary" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save Profile' }}
        </button>
        <button type="button" class="ml-btn-ghost" :disabled="saving" @click="cancelEditing">Cancel</button>
      </div>
    </form>

    <template v-else>
      <div class="mt-6 flex items-start gap-4">
        <div class="h-16 w-16 rounded-2xl border border-ink-200 bg-ink-50 overflow-hidden flex items-center justify-center shrink-0">
          <img
            v-if="profile?.logo_url"
            :src="profile.logo_url"
            :alt="`${profile.business_name} logo`"
            class="h-full w-full object-cover"
          />
          <span v-else class="text-[10px] font-bold uppercase tracking-wide text-ink-400 text-center px-1">Logo</span>
        </div>
        <div class="min-w-0">
          <p class="text-lg font-bold text-ink-900 truncate">{{ profile?.business_name || '—' }}</p>
          <p v-if="profile?.business_category" class="text-sm text-brand-700 font-semibold mt-0.5">
            {{ profile.business_category }}
          </p>
        </div>
      </div>

      <dl class="mt-6 space-y-4">
        <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
          <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Phone</dt>
          <dd class="mt-1 text-base font-semibold text-ink-900">{{ profile?.business_phone || '—' }}</dd>
        </div>
        <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
          <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Email</dt>
          <dd class="mt-1 text-base font-semibold text-ink-900">{{ account.email || '—' }}</dd>
        </div>
        <div v-if="profile?.description" class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
          <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Description</dt>
          <dd class="mt-1 text-sm text-ink-700 whitespace-pre-line">{{ profile.description }}</dd>
        </div>
        <div v-else class="rounded-xl border border-dashed border-ink-200 bg-ink-50/30 p-4 text-sm text-ink-500">
          Add a business description to help customers learn about your booth.
        </div>
      </dl>
    </template>
  </aside>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../services/api';
import { extractApiError } from '../utils/apiErrors';
import { PRODUCT_CATEGORIES } from '../utils/bookingDisplay';

const emit = defineEmits(['loaded', 'updated']);

const toast = useToast();

const profile = ref(null);
const account = ref({ email: '', name: '', phone_number: '' });
const loading = ref(false);
const loadError = ref(false);
const editing = ref(false);
const saving = ref(false);
const errors = reactive({});
const logoInput = ref(null);
const logoFile = ref(null);
const logoPreviewUrl = ref('');
const removeLogo = ref(false);
let objectPreviewUrl = '';

const form = reactive({
  business_name: '',
  business_phone: '',
  business_category: '',
  description: '',
});

const revokeObjectPreview = () => {
  if (objectPreviewUrl) {
    URL.revokeObjectURL(objectPreviewUrl);
    objectPreviewUrl = '';
  }
};

const fillForm = () => {
  form.business_name = profile.value?.business_name || '';
  form.business_phone = profile.value?.business_phone || '';
  form.business_category = profile.value?.business_category || '';
  form.description = profile.value?.description || '';
  logoPreviewUrl.value = profile.value?.logo_url || '';
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

const loadProfile = async () => {
  loading.value = true;
  loadError.value = false;
  try {
    const { data } = await api.get('/vendor/business-profile');
    profile.value = data.profile;
    account.value = data.account || {};
    emit('loaded', data.profile);
  } catch (error) {
    console.error('Unable to load vendor business profile:', error);
    loadError.value = true;
  } finally {
    loading.value = false;
  }
};

const startEditing = () => {
  fillForm();
  logoFile.value = null;
  removeLogo.value = false;
  clearErrors();
  editing.value = true;
};

const cancelEditing = () => {
  revokeObjectPreview();
  logoFile.value = null;
  removeLogo.value = false;
  if (logoInput.value) logoInput.value.value = '';
  fillForm();
  clearErrors();
  editing.value = false;
};

const onLogoSelected = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  logoFile.value = file;
  removeLogo.value = false;
  revokeObjectPreview();
  objectPreviewUrl = URL.createObjectURL(file);
  logoPreviewUrl.value = objectPreviewUrl;
};

const clearLogoSelection = () => {
  logoFile.value = null;
  removeLogo.value = true;
  revokeObjectPreview();
  logoPreviewUrl.value = '';
  if (logoInput.value) logoInput.value.value = '';
};

const uploadLogoIfNeeded = async () => {
  if (!logoFile.value && !removeLogo.value) return;

  const fd = new FormData();
  if (logoFile.value) {
    fd.append('logo', logoFile.value);
  }
  if (removeLogo.value) {
    fd.append('remove_logo', '1');
  }

  const { data } = await api.post('/vendor/business-profile/logo', fd);
  profile.value = data.profile;
};

const saveProfile = async () => {
  saving.value = true;
  clearErrors();
  try {
    const { data } = await api.put('/vendor/business-profile', {
      business_name: form.business_name.trim(),
      business_phone: form.business_phone?.trim() || null,
      business_category: form.business_category || null,
      description: form.description?.trim() || null,
    });

    profile.value = data.profile;
    account.value = data.account || account.value;

    await uploadLogoIfNeeded();

    emit('updated', profile.value);
    toast.success('Business profile saved.');
    editing.value = false;
    logoFile.value = null;
    removeLogo.value = false;
    if (logoInput.value) logoInput.value.value = '';
  } catch (error) {
    console.error('Unable to save vendor business profile:', error);
    applyValidationErrors(error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};

onMounted(loadProfile);

defineExpose({ startEditing });
</script>
