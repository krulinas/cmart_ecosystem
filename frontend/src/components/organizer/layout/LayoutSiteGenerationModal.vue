<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="layout-generate-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="close" />
      <div class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" data-testid="layout-site-generate-modal">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <div>
            <h2 id="layout-generate-title" class="text-lg font-extrabold text-ink-900">{{ copy.generateSites }}</h2>
            <p class="text-xs text-ink-500">{{ row?.label }} · {{ row?.category?.label }}</p>
          </div>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
        </header>

        <form class="space-y-4 p-5" @submit.prevent="submit">
          <p class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
            {{ copy.generateAtomicHint }}
          </p>

          <div>
            <label class="ml-label">Space type</label>
            <select v-model.number="form.space_id" class="ml-input" required :disabled="submitting" data-testid="layout-generate-space-select">
              <option disabled value="">{{ copy.selectSpaceType }}</option>
              <option v-for="space in spaces" :key="space.id" :value="space.id">
                {{ space.space_size }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="ml-label">Label prefix</label>
              <input v-model="form.label_prefix" class="ml-input" required maxlength="16" :disabled="submitting" data-testid="layout-generate-prefix-input" />
            </div>
            <div>
              <label class="ml-label">Count</label>
              <input v-model.number="form.count" type="number" min="1" :max="maxCount" class="ml-input" required :disabled="submitting" data-testid="layout-generate-count-input" />
            </div>
            <div>
              <label class="ml-label">Start number</label>
              <input v-model.number="form.start_number" type="number" min="1" class="ml-input" :disabled="submitting" />
            </div>
            <div>
              <label class="ml-label">Number padding</label>
              <input v-model.number="form.number_padding" type="number" min="1" max="6" class="ml-input" :disabled="submitting" />
            </div>
          </div>

          <div class="rounded-xl border border-ink-200 bg-ink-50 px-3 py-3">
            <div class="text-xs font-bold uppercase tracking-wider text-ink-500">Preview</div>
            <p class="mt-2 break-words text-sm font-semibold text-ink-900" data-testid="layout-generate-preview">
              {{ previewText }}
            </p>
          </div>

          <p v-if="formError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ formError }}</p>

          <div class="flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">{{ copy.cancel }}</button>
            <button type="submit" class="ml-btn-primary" :disabled="submitting || preview.length === 0" data-testid="layout-generate-submit">
              {{ submitting ? copy.generating : copy.generateSitesAction(preview.length || 0) }}
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
import { MAX_GENERATED_SITES, previewGeneratedLabels } from '../../../utils/organizerEventLayoutHelpers';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  row: { type: Object, default: null },
  spaces: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  formError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'submit']);
const copy = LAYOUT_COPY;
const maxCount = MAX_GENERATED_SITES;

const form = reactive({
  space_id: '',
  label_prefix: 'A',
  count: 5,
  start_number: 1,
  number_padding: 2,
  start_grid_column: 1,
  display_order_start: 1,
});

watch(
  () => [props.modelValue, props.row],
  () => {
    if (!props.modelValue) return;
    const label = String(props.row?.label || 'A').trim();
    form.label_prefix = (label.match(/^[A-Za-z0-9]+/) || ['A'])[0].slice(0, 8).toUpperCase();
    form.space_id = props.spaces[0]?.id || '';
    form.count = 5;
    form.start_number = 1;
    form.number_padding = 2;
    form.start_grid_column = 1;
    form.display_order_start = (props.row?.sites?.length || 0) + 1;
  },
  { immediate: true },
);

const preview = computed(() => previewGeneratedLabels(form));
const previewText = computed(() => (preview.value.length ? preview.value.join(', ') : copy.noPreview));

function close() {
  if (props.submitting) return;
  emit('update:modelValue', false);
}

function submit() {
  const count = Number(form.count);
  if (!window.confirm(copy.confirmGenerateSites(count, props.row?.label))) {
    return;
  }
  emit('submit', {
    space_id: Number(form.space_id),
    count,
    label_prefix: String(form.label_prefix || '').trim().toUpperCase(),
    start_number: Number(form.start_number) || 1,
    number_padding: Number(form.number_padding) || 2,
    start_grid_column: Number(form.start_grid_column) || 1,
    display_order_start: Number(form.display_order_start) || 1,
  });
}
</script>
