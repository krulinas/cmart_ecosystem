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
        aria-labelledby="vendor-profile-edit-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" aria-hidden="true" @click="close" />

        <div
          class="relative z-10 w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 p-6 sm:p-8"
          @click.stop
        >
          <button
            type="button"
            class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white text-ink-500 shadow-md ring-1 ring-ink-200 hover:bg-ink-50"
            :aria-label="t('profile.edit.closeAria')"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <h2 id="vendor-profile-edit-title" class="text-2xl font-extrabold text-ink-900 pr-10">{{ t('profile.edit.title') }}</h2>
          <p class="mt-1 text-sm text-ink-500">{{ t('profile.edit.subtitle') }}</p>

          <form class="mt-6 space-y-5" @submit.prevent="save">
            <div class="flex flex-col sm:flex-row gap-4 items-start">
              <div class="shrink-0">
                <div class="h-20 w-20 rounded-2xl border border-ink-200 bg-ink-50 overflow-hidden flex items-center justify-center">
                  <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Logo preview" class="h-full w-full object-cover" />
                  <span v-else class="text-xs font-bold text-ink-400 text-center px-2">{{ t('profile.edit.noLogo') }}</span>
                </div>
                <input ref="logoInput" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" @change="onLogoSelected" />
                <div class="mt-2 flex flex-wrap gap-2">
                  <button type="button" class="ml-btn-ghost text-xs" @click="logoInput?.click()">{{ t('profile.edit.uploadLogo') }}</button>
                  <button v-if="logoPreviewUrl" type="button" class="ml-btn-ghost text-xs text-rose-600" @click="clearLogo">{{ t('profile.edit.remove') }}</button>
                </div>
              </div>

              <div class="flex-1 w-full space-y-4">
                <div>
                  <label class="ml-label">{{ t('profile.edit.fullName') }}</label>
                  <input v-model="form.name" class="ml-input" required />
                  <p v-if="errors.name" class="mt-1 text-xs text-rose-600">{{ errors.name }}</p>
                </div>
                <div>
                  <label class="ml-label">{{ t('profile.edit.phone') }}</label>
                  <input v-model="form.phone_number" class="ml-input" />
                  <p v-if="errors.phone_number" class="mt-1 text-xs text-rose-600">{{ errors.phone_number }}</p>
                </div>
              </div>
            </div>

            <div>
              <label class="ml-label">{{ t('profile.edit.businessName') }}</label>
              <input v-model="form.business_name" class="ml-input" required />
              <p v-if="errors.business_name" class="mt-1 text-xs text-rose-600">{{ errors.business_name }}</p>
            </div>

            <div>
              <label class="ml-label">{{ t('profile.edit.businessPhone') }}</label>
              <input v-model="form.business_phone" class="ml-input" />
              <p v-if="errors.business_phone" class="mt-1 text-xs text-rose-600">{{ errors.business_phone }}</p>
            </div>

            <label class="flex items-start gap-2 rounded-xl border border-ink-100 bg-ink-50/50 p-3 text-sm text-ink-700">
              <input
                v-model="form.marketplace_whatsapp_enabled"
                type="checkbox"
                class="mt-1"
                data-testid="vendor-whatsapp-opt-in"
              />
              <span>
                <span class="font-semibold text-ink-800">{{ t('profile.edit.whatsappOptIn') }}</span>
                <span class="mt-0.5 block text-xs text-ink-500">
                  {{ t('profile.edit.whatsappOptInHint') }}
                </span>
              </span>
            </label>
            <p v-if="errors.marketplace_whatsapp_enabled" class="text-xs text-rose-600">
              {{ errors.marketplace_whatsapp_enabled }}
            </p>

            <div>
              <label class="ml-label">{{ t('profile.edit.businessCategory') }}</label>
              <select v-model="form.business_category" class="ml-input">
                <option value="">{{ t('profile.edit.selectCategory') }}</option>
                <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">{{ category }}</option>
              </select>
            </div>

            <div>
              <label class="ml-label">{{ t('profile.edit.businessDescription') }}</label>
              <textarea v-model="form.description" rows="4" class="ml-input" :placeholder="t('profile.edit.descriptionPlaceholder')"></textarea>
              <p v-if="errors.description" class="mt-1 text-xs text-rose-600">{{ errors.description }}</p>
            </div>

            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-3 text-sm text-ink-600">
              <p>Email: <span class="font-semibold text-ink-900">{{ profile?.email || '—' }}</span> <span class="text-ink-400">{{ t('profile.edit.emailReadOnly') }}</span></p>
              <p class="mt-1">{{ t('profile.edit.vendorStatusPrefix') }} <span class="font-semibold text-ink-900">{{ vendorStatusLabel }}</span> <span class="text-ink-400">{{ t('profile.edit.emailReadOnly') }}</span></p>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
              <button type="submit" class="ml-btn-primary" :disabled="saving">{{ saving ? t('profile.edit.saving') : t('profile.edit.saveChanges') }}</button>
              <button type="button" class="ml-btn-ghost" :disabled="saving" @click="close">{{ t('profile.edit.cancel') }}</button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import api from '../services/api';
import { extractApiError } from '../utils/apiErrors';
import { PRODUCT_CATEGORIES } from '../utils/bookingDisplay';
import {
  WHATSAPP_OPT_IN_PHONE_REQUIRED,
  normalizeWhatsAppNumber,
} from '../utils/whatsappContact';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  profile: { type: Object, default: null },
  vendorStatus: { type: String, default: 'none' },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const { t } = useI18n();
const toast = useToast();
const saving = ref(false);
const errors = reactive({});
const logoInput = ref(null);
const logoFile = ref(null);
const removeLogo = ref(false);
const logoPreviewUrl = ref('');
let objectPreviewUrl = '';

const form = reactive({
  name: '',
  phone_number: '',
  business_name: '',
  business_phone: '',
  marketplace_whatsapp_enabled: false,
  business_category: '',
  description: '',
});

const vendorStatusLabel = computed(() => {
  const status = props.vendorStatus;
  if (status === 'approved') return 'Approved Vendor';
  if (status === 'pending') return 'Pending Approval';
  if (status === 'rejected') return 'Not Approved';
  return t('profile.statusCommunity');
});

const revokeObjectPreview = () => {
  if (objectPreviewUrl) {
    URL.revokeObjectURL(objectPreviewUrl);
    objectPreviewUrl = '';
  }
};

const fillForm = () => {
  const p = props.profile;
  form.name = p?.name || '';
  form.phone_number = p?.phone_number || '';
  form.business_name = p?.business_name || p?.name || '';
  form.business_phone = p?.business_phone || p?.phone_number || '';
  form.marketplace_whatsapp_enabled = Boolean(p?.marketplace_whatsapp_enabled);
  form.business_category = p?.business_category || '';
  form.description = p?.description || '';
  logoPreviewUrl.value = p?.logo_url || '';
  logoFile.value = null;
  removeLogo.value = false;
};

const clearErrors = () => {
  Object.keys(errors).forEach((key) => delete errors[key]);
};

const applyValidationErrors = (error) => {
  clearErrors();
  const validationErrors = error?.response?.data?.errors;
  if (!validationErrors) return;
  Object.entries(validationErrors).forEach(([field, messages]) => {
    errors[field] = messages?.[0];
  });
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

const clearLogo = () => {
  logoFile.value = null;
  removeLogo.value = true;
  revokeObjectPreview();
  logoPreviewUrl.value = '';
  if (logoInput.value) logoInput.value.value = '';
};

const close = () => {
  emit('update:modelValue', false);
};

const save = async () => {
  clearErrors();

  if (form.marketplace_whatsapp_enabled && !normalizeWhatsAppNumber(form.business_phone)) {
    errors.marketplace_whatsapp_enabled = WHATSAPP_OPT_IN_PHONE_REQUIRED;
    errors.business_phone = WHATSAPP_OPT_IN_PHONE_REQUIRED;
    toast.error(WHATSAPP_OPT_IN_PHONE_REQUIRED);
    return;
  }

  saving.value = true;

  try {
    const fd = new FormData();
    fd.append('name', form.name.trim());
    fd.append('phone_number', form.phone_number?.trim() || '');
    fd.append('business_name', form.business_name.trim());
    fd.append('business_phone', form.business_phone?.trim() || '');
    fd.append('marketplace_whatsapp_enabled', form.marketplace_whatsapp_enabled ? '1' : '0');
    if (form.business_category) fd.append('business_category', form.business_category);
    if (form.description?.trim()) fd.append('description', form.description.trim());
    if (logoFile.value) fd.append('logo', logoFile.value);
    if (removeLogo.value) fd.append('remove_logo', '1');
    fd.append('_method', 'PATCH');

    const { data } = await api.post('/vendor/profile', fd);
    toast.success(t('profile.edit.toastUpdated'));
    emit('saved', data);
    close();
  } catch (error) {
    console.error('Unable to save vendor profile:', error);
    applyValidationErrors(error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      fillForm();
      clearErrors();
    } else {
      revokeObjectPreview();
    }
  },
);
</script>
