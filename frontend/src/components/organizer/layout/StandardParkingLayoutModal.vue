<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="standard-layout-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="close" />
      <div
        class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-xl sm:rounded-2xl"
        data-testid="standard-parking-layout-modal"
      >
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <div>
            <h2 id="standard-layout-title" class="text-lg font-extrabold text-ink-900">
              {{ copy.generateStandardLayout }}
            </h2>
            <p class="text-xs text-ink-500">{{ copy.generateStandardLayoutHelp }}</p>
          </div>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">
            {{ copy.cancel }}
          </button>
        </header>

        <form class="space-y-4 p-5" @submit.prevent="submit">
          <p class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
            {{ copy.generateStandardConfirm }}
          </p>

          <p
            class="rounded-xl border border-ink-200 bg-ink-50 px-3 py-2 text-sm text-ink-800"
            data-testid="standard-layout-open-limit"
          >
            {{ copy.generateStandardOpenCount(null) }}
          </p>

          <div class="grid gap-3 sm:grid-cols-2">
            <div v-for="rowLabel in rowLabels" :key="rowLabel">
              <label class="ml-label" :for="`standard-layout-category-${rowLabel}`">
                {{ categoryLabel(rowLabel) }}
              </label>
              <select
                :id="`standard-layout-category-${rowLabel}`"
                v-model.number="form.row_categories[rowLabel]"
                class="ml-input"
                required
                :disabled="submitting"
                :data-testid="`standard-layout-category-${rowLabel}`"
              >
                <option disabled value="">{{ copy.selectCategory }}</option>
                <option
                  v-for="category in categories"
                  :key="`${rowLabel}-${category.id}`"
                  :value="category.id"
                >
                  {{ category.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="rounded-xl border border-ink-200 bg-ink-50 px-3 py-3">
            <div class="text-xs font-bold uppercase tracking-wider text-ink-500">Preview</div>
            <p class="mt-2 text-sm font-semibold text-ink-900" data-testid="standard-layout-preview">
              4 rows × 16 sites = 64 physical sites (initially NOT OPEN)
            </p>
            <p class="mt-1 break-words text-xs text-ink-600">
              {{ previewLabels.slice(0, 8).join(', ') }} … {{ previewLabels.slice(-4).join(', ') }}
            </p>
            <p class="mt-2 text-xs text-ink-500">
              Exit above Row A · Vehicle aisle between B and C · Entrance below Row D
            </p>
          </div>

          <p
            v-if="formError"
            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
            data-testid="standard-layout-error"
          >
            {{ formError }}
          </p>

          <div class="flex justify-end gap-2">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">
              {{ copy.cancel }}
            </button>
            <button
              type="submit"
              class="ml-btn-primary"
              :disabled="submitting || !canSubmit"
              data-testid="standard-layout-submit"
            >
              {{ submitting ? copy.generating : copy.generateStandardLayout }}
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
import { CMART_CARBOOT_ROW_LABELS } from '../../../config/cmartCarbootPhysicalLayout';
import { previewStandardParkingLabels } from '../../../utils/visualParkingLayout';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  formError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const copy = LAYOUT_COPY;
const rowLabels = CMART_CARBOOT_ROW_LABELS;
const previewLabels = previewStandardParkingLabels();

const form = reactive({
  row_categories: {
    A: '',
    B: '',
    C: '',
    D: '',
  },
});

const canSubmit = computed(() => rowLabels.every((label) => Number(form.row_categories[label]) > 0));

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return;
    const firstCategory = props.categories[0]?.id || '';
    for (const label of rowLabels) {
      form.row_categories[label] = firstCategory;
    }
  },
);

function categoryLabel(rowLabel) {
  return ({
    A: copy.rowCategoryA,
    B: copy.rowCategoryB,
    C: copy.rowCategoryC,
    D: copy.rowCategoryD,
  })[rowLabel];
}

function close() {
  if (props.submitting) return;
  emit('update:modelValue', false);
}

function submit() {
  if (!canSubmit.value || props.submitting) return;
  emit('submit', {
    row_categories: {
      A: Number(form.row_categories.A),
      B: Number(form.row_categories.B),
      C: Number(form.row_categories.C),
      D: Number(form.row_categories.D),
    },
  });
}
</script>
