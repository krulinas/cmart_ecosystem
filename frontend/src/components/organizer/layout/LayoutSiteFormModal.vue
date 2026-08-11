<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="layout-site-form-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="close" />
      <div class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" data-testid="layout-site-form-modal">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <div>
            <h2 id="layout-site-form-title" class="text-lg font-extrabold text-ink-900">
              {{ isEdit ? copy.editSite : copy.addSite }}
            </h2>
            <p v-if="rowLabel" class="text-xs text-ink-500">{{ copy.rowLabelPrefix }}: {{ rowLabel }} · {{ rowCategory }}</p>
          </div>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
        </header>

        <form class="space-y-4 p-5" @submit.prevent="submit">
          <p v-if="structureLocked" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            {{ copy.structureLockedHint }}
          </p>

          <div>
            <label class="ml-label" for="layout-site-label">Site label</label>
            <input id="layout-site-label" v-model="form.label" class="ml-input" required maxlength="32" :disabled="submitting || structureLocked" data-testid="layout-site-label-input" />
          </div>

          <div v-if="isEdit && rows.length" class="space-y-1">
            <label class="ml-label" for="layout-site-row">{{ copy.targetRow }}</label>
            <select id="layout-site-row" v-model.number="form.event_layout_row_id" class="ml-input" :disabled="submitting || structureLocked" data-testid="layout-site-row-select">
              <option v-for="row in movableRows" :key="row.id" :value="row.id">
                {{ row.label }} — {{ row.category?.label || copy.noCategory }}
              </option>
            </select>
          </div>

          <div>
            <label class="ml-label" for="layout-site-status">Status</label>
            <select id="layout-site-status" v-model="form.operational_status" class="ml-input" :disabled="submitting">
              <option value="active">Active</option>
              <option value="unavailable">Unavailable</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>

          <details class="rounded-xl border border-ink-200 p-3">
            <summary class="cursor-pointer text-sm font-bold text-ink-800">{{ copy.advanced }}</summary>
            <div class="mt-3 grid grid-cols-2 gap-3">
              <div>
                <label class="ml-label">Position</label>
                <input v-model.number="form.position_number" type="number" min="1" class="ml-input" :disabled="submitting || structureLocked" />
              </div>
              <div>
                <label class="ml-label">{{ copy.displayOrder }}</label>
                <input v-model.number="form.display_order" type="number" min="0" class="ml-input" :disabled="submitting" />
              </div>
              <div>
                <label class="ml-label">Grid row</label>
                <input v-model.number="form.grid_row" type="number" min="0" class="ml-input" :disabled="submitting || structureLocked" />
              </div>
              <div>
                <label class="ml-label">Grid column</label>
                <input v-model.number="form.grid_column" type="number" min="0" class="ml-input" :disabled="submitting || structureLocked" />
              </div>
            </div>
          </details>

          <p v-if="formError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ formError }}</p>

          <div class="flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
            <button type="submit" class="ml-btn-primary" :disabled="submitting" data-testid="layout-site-form-submit">
              {{ submitting ? 'Menyimpan…' : copy.save }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { LAYOUT_COPY } from '../../../utils/organizerEventLayoutMessages';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  site: { type: Object, default: null },
  row: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  formError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'submit']);
const copy = LAYOUT_COPY;
const isEdit = computed(() => Boolean(props.site?.id));
const structureLocked = computed(() => Boolean(props.site?.locks?.structure_locked));
const rowLabel = computed(() => props.row?.label || props.site?.row_label || '');
const rowCategory = computed(() => props.row?.category?.label || '');
const movableRows = computed(() => props.rows.filter((row) => row.is_active && !row.archived_at));

const form = reactive({
  label: '',
  event_layout_row_id: '',
  position_number: 1,
  grid_row: 1,
  grid_column: 1,
  display_order: 1,
  operational_status: 'active',
});

watch(
  () => [props.modelValue, props.site, props.row],
  () => {
    if (!props.modelValue) return;
    form.label = props.site?.label || '';
    form.event_layout_row_id = props.site?.event_layout_row_id || props.row?.id || '';
    form.position_number = props.site?.position_number || ((props.row?.sites?.length || 0) + 1);
    form.grid_row = props.site?.grid_row ?? 1;
    form.grid_column = props.site?.grid_column || form.position_number;
    form.display_order = props.site?.display_order || form.position_number;
    form.operational_status = props.site?.operational_status || 'active';
  },
  { immediate: true },
);

function close() {
  if (props.submitting) return;
  emit('update:modelValue', false);
}

function submit() {
  const payload = {
    display_order: Number(form.display_order),
    operational_status: form.operational_status,
  };

  if (!structureLocked.value) {
    payload.label = String(form.label || '').trim().toUpperCase();
    payload.position_number = Number(form.position_number);
    payload.grid_row = Number(form.grid_row);
    payload.grid_column = Number(form.grid_column);
    if (isEdit.value) {
      payload.event_layout_row_id = Number(form.event_layout_row_id);
    }
  }

  emit('submit', payload);
}
</script>
