<template>
  <section class="rounded-xl border border-sky-100 bg-white p-4" data-testid="survey-import-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-sm font-extrabold text-ink-900">Vendor survey CSV</h3>
        <p class="mt-1 text-sm text-ink-500">
          Upload post-event vendor responses for the selected event.
          Duplicate files are rejected; a different file replaces the active dataset after confirmation.
        </p>
      </div>
      <label
        class="ml-btn-primary cursor-pointer text-sm"
        :class="{ 'pointer-events-none opacity-50': !eventId || uploading }"
        :title="!eventId ? 'Select an event first' : 'Upload vendor survey CSV'"
      >
        <input
          ref="fileInput"
          type="file"
          accept=".csv,text/csv"
          class="hidden"
          :disabled="!eventId || uploading"
          @change="onFileChange"
        />
        {{ uploading ? 'Uploading…' : 'Upload CSV' }}
      </label>
    </div>

    <p v-if="info" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
      {{ info }}
    </p>
    <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
      {{ error }}
    </p>

    <div
      v-if="replacePrompt"
      class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950"
      data-testid="survey-replace-confirm"
    >
      <p class="font-bold">Replace existing survey dataset?</p>
      <p class="mt-1">
        A survey dataset already exists for this event. Replace it with the newly validated file?
        <template v-if="replacePrompt.active?.original_filename">
          Current file: {{ replacePrompt.active.original_filename }}
          (n = {{ replacePrompt.active.valid_row_count ?? '—' }}).
        </template>
      </p>
      <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="ml-btn-primary text-sm" :disabled="uploading" @click="confirmReplace">
          Replace dataset
        </button>
        <button type="button" class="ml-btn-ghost text-sm" :disabled="uploading" @click="cancelReplace">
          Cancel
        </button>
      </div>
    </div>

    <div v-if="latest" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg bg-sky-50 px-3 py-2">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Active import</p>
        <p class="font-bold capitalize text-ink-900">{{ humanStatus(latest.status) }}</p>
      </div>
      <div class="rounded-lg bg-sky-50 px-3 py-2">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Valid rows</p>
        <p class="font-bold text-ink-900">{{ latest.valid_row_count }} / {{ latest.total_row_count }}</p>
      </div>
      <div class="rounded-lg bg-sky-50 px-3 py-2">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Invalid rows</p>
        <p class="font-bold text-ink-900">{{ latest.invalid_row_count }}</p>
      </div>
      <div class="rounded-lg bg-sky-50 px-3 py-2">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">File</p>
        <p class="truncate font-bold text-ink-900" :title="latest.original_filename">
          {{ latest.original_filename || '—' }}
        </p>
      </div>
    </div>

    <p v-if="latest?.schema_version || latest?.schema_name" class="mt-2 text-xs text-ink-500">
      Survey format: {{ latest.schema_name || 'vendor survey' }}
      <template v-if="latest.schema_version"> · version {{ latest.schema_version }}</template>
      <template v-if="latest.id"> · batch #{{ latest.id }}</template>
    </p>

    <div v-if="rowErrors.length" class="mt-4">
      <p class="text-sm font-bold text-ink-800">Rows needing attention (first {{ rowErrors.length }})</p>
      <ul class="mt-2 max-h-40 space-y-1 overflow-auto text-xs text-rose-800">
        <li v-for="(err, idx) in rowErrors" :key="idx" class="rounded bg-rose-50 px-2 py-1">
          Row {{ err.source_row_number }} · {{ err.field }} — {{ err.message }}
        </li>
      </ul>
    </div>

    <p v-else-if="!latest && !uploading" class="mt-3 text-sm text-ink-500">
      No active survey import for this event yet.
    </p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { listSurveyImports, uploadSurveyImport } from '../../services/eventAnalyticsApi';

const props = defineProps({
  eventId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['imported']);

const toast = useToast();
const uploading = ref(false);
const error = ref('');
const info = ref('');
const latest = ref(null);
const replacePrompt = ref(null);
const pendingFile = ref(null);
const fileInput = ref(null);

const rowErrors = computed(() => {
  const errors = latest.value?.validation_summary?.row_errors;
  return Array.isArray(errors) ? errors.slice(0, 25) : [];
});

const humanStatus = (status) => {
  if (!status) return '—';
  return String(status).replace(/_/g, ' ');
};

const loadLatest = async () => {
  if (!props.eventId) {
    latest.value = null;
    return;
  }
  error.value = '';
  try {
    const { data } = await listSurveyImports(props.eventId);
    const rows = Array.isArray(data) ? data : data?.data || [];
    latest.value = rows.find((row) => row.is_active) || rows.find((row) => ['completed', 'completed_with_errors'].includes(row.status)) || null;
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to load survey imports.';
    latest.value = null;
  }
};

const upload = async (file, replaceExisting = false) => {
  uploading.value = true;
  error.value = '';
  info.value = '';
  try {
    const { data } = await uploadSurveyImport(props.eventId, file, { replaceExisting });
    latest.value = data?.data || data;
    replacePrompt.value = null;
    pendingFile.value = null;
    toast.success(replaceExisting ? 'Survey dataset replaced.' : 'Survey import completed.');
    emit('imported', latest.value);
  } catch (e) {
    const payload = e.response?.data || {};
    const code = payload.code;
    if (e.response?.status === 409 && code === 'survey_import_duplicate') {
      info.value = 'This CSV has already been imported for this event. No duplicate responses were added.';
      replacePrompt.value = null;
      pendingFile.value = null;
      toast.info(info.value);
      return;
    }
    if (e.response?.status === 409 && code === 'survey_import_replace_required') {
      pendingFile.value = file;
      replacePrompt.value = {
        active: payload.active_batch || null,
        message: payload.message,
      };
      return;
    }
    error.value = payload.message
      || payload.errors?.file?.[0]
      || 'Survey import failed.';
    toast.error(error.value);
  } finally {
    uploading.value = false;
  }
};

const onFileChange = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !props.eventId) return;
  await upload(file, false);
};

const confirmReplace = async () => {
  if (!pendingFile.value) return;
  await upload(pendingFile.value, true);
};

const cancelReplace = () => {
  replacePrompt.value = null;
  pendingFile.value = null;
};

const openFilePicker = () => {
  fileInput.value?.click();
};

watch(() => props.eventId, () => {
  replacePrompt.value = null;
  pendingFile.value = null;
  info.value = '';
  loadLatest();
}, { immediate: true });

defineExpose({ reload: loadLatest, openFilePicker });
</script>
