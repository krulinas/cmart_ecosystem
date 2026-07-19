<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="layout-row-form-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="close" />
      <div class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" data-testid="layout-row-form-modal">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <h2 id="layout-row-form-title" class="text-lg font-extrabold text-ink-900">
            {{ isEdit ? copy.editRow : copy.addRow }}
          </h2>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
        </header>

        <form class="space-y-4 p-5" @submit.prevent="submit">
          <div>
            <label class="ml-label" for="layout-row-label">Row name</label>
            <input
              id="layout-row-label"
              v-model="form.label"
              class="ml-input"
              required
              maxlength="32"
              :disabled="submitting || renameLocked"
              data-testid="layout-row-label-input"
            />
            <p v-if="renameLocked" class="mt-1 text-xs text-amber-800">{{ copy.renameLockedHint }}</p>
            <p v-if="fieldErrors.label" class="mt-1 text-xs text-rose-700">{{ fieldErrors.label }}</p>
          </div>

          <div>
            <label class="ml-label" for="layout-row-category">Category</label>
            <select
              id="layout-row-category"
              v-model.number="form.vendor_category_id"
              class="ml-input"
              required
              :disabled="submitting || categoryLocked"
              data-testid="layout-row-category-select"
            >
              <option disabled value="">{{ copy.selectCategory }}</option>
              <option
                v-for="category in categories"
                :key="category.id"
                :value="category.id"
                :disabled="!isCategorySelectable(category)"
              >
                {{ category.label }}{{ isCategorySelectable(category) ? '' : ' (inactive)' }}
              </option>
            </select>
            <p v-if="categoryLocked" class="mt-1 text-xs text-amber-800">{{ copy.categoryLockedHint }}</p>
          </div>

          <div>
            <label class="ml-label" for="layout-row-description">Description</label>
            <textarea id="layout-row-description" v-model="form.description" rows="3" class="ml-input" :disabled="submitting" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <label class="flex items-center gap-2 text-sm text-ink-800">
              <input v-model="form.is_active" type="checkbox" class="rounded border-ink-300" :disabled="submitting || Boolean(row?.archived_at)" />
              Active
            </label>
            <label class="flex items-center gap-2 text-sm text-ink-800">
              <input v-model="form.is_public" type="checkbox" class="rounded border-ink-300" :disabled="submitting" />
              Public display
            </label>
          </div>

          <p v-if="formError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-testid="layout-row-form-error">
            {{ formError }}
          </p>

          <div class="flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
            <button type="submit" class="ml-btn-primary" :disabled="submitting" data-testid="layout-row-form-submit">
              {{ submitting ? 'Menyimpan…' : copy.save }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { reactive, watch, computed } from 'vue';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  row: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  formError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const copy = LAYOUT_COPY;
const isEdit = computed(() => Boolean(props.row?.id));
const renameLocked = computed(() => Boolean(props.row?.locks?.rename_locked));
const categoryLocked = computed(() => Boolean(props.row?.locks?.category_change_locked));

const form = reactive({
  label: '',
  vendor_category_id: '',
  description: '',
  is_active: true,
  is_public: true,
});

const fieldErrors = reactive({
  label: '',
});

watch(
  () => [props.modelValue, props.row],
  () => {
    if (!props.modelValue) return;
    form.label = props.row?.label || '';
    form.vendor_category_id = props.row?.category?.id || props.row?.vendor_category_id || '';
    form.description = props.row?.description || '';
    form.is_active = props.row ? Boolean(props.row.is_active) : true;
    form.is_public = props.row ? Boolean(props.row.is_public) : true;
    fieldErrors.label = '';
  },
  { immediate: true },
);

function isCategorySelectable(category) {
  if (isEdit.value && Number(category.id) === Number(form.vendor_category_id)) {
    return true;
  }
  return Boolean(category.selectable_for_new_row ?? (category.is_active && !category.archived_at));
}

function close() {
  if (props.submitting) return;
  emit('update:modelValue', false);
}

function submit() {
  fieldErrors.label = '';
  const label = String(form.label || '').trim();
  if (!label) {
    fieldErrors.label = 'Row name is required.';
    return;
  }

  const payload = {
    description: form.description || null,
    is_public: Boolean(form.is_public),
  };

  if (!renameLocked.value) {
    payload.label = label;
  }
  if (!categoryLocked.value) {
    payload.vendor_category_id = Number(form.vendor_category_id);
  }
  if (!props.row?.archived_at) {
    payload.is_active = Boolean(form.is_active);
  }

  emit('submit', payload);
}
</script>
