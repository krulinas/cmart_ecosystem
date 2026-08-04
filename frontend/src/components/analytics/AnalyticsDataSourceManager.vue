<template>
  <section class="space-y-4" data-testid="analytics-data-sources">
    <!-- Source mode -->
    <div class="rounded-xl border border-sky-100 bg-white p-4">
      <h3 class="text-sm font-extrabold text-ink-900">Analytics source</h3>
      <p class="mt-1 text-sm text-ink-500">
        Choose what Analytics Hub and new event reports include for this event.
      </p>

      <div class="mt-4 grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Analytics source mode">
        <button
          v-for="opt in modeOptions"
          :key="opt.value"
          type="button"
          role="radio"
          :aria-checked="selectedMode === opt.value"
          class="rounded-xl border px-3 py-3 text-left transition"
          :class="selectedMode === opt.value
            ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600'
            : 'border-ink-200 bg-white hover:border-brand-300'"
          :disabled="busy || !eventId"
          @click="changeMode(opt.value)"
        >
          <p class="text-sm font-bold text-ink-900">{{ opt.label }}</p>
          <p class="mt-1 text-xs text-ink-500">{{ opt.hint }}</p>
        </button>
      </div>
    </div>

    <!-- System Data status -->
    <div class="rounded-xl border border-sky-100 bg-white p-4">
      <h3 class="text-sm font-extrabold text-ink-900">Current System Data</h3>
      <p class="mt-1 text-sm text-ink-500">
        Bookings, payments, event spaces and reservations recorded in the system.
      </p>
      <p class="mt-3 text-sm font-semibold" :class="systemIncluded ? 'text-emerald-700' : 'text-ink-500'">
        {{ systemIncluded ? 'Available' : 'Excluded by current mode' }}
      </p>
    </div>

    <!-- Survey CSV -->
    <div class="rounded-xl border border-sky-100 bg-white p-4" data-testid="current-csv-source">
      <h3 class="text-sm font-extrabold text-ink-900">Current Survey CSV</h3>

      <input
        ref="fileInput"
        type="file"
        accept=".csv,text/csv"
        class="hidden"
        :disabled="!eventId || busy"
        @change="onFileChange"
      />

      <template v-if="currentCsv">
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Filename</dt>
            <dd class="mt-0.5 truncate font-semibold text-ink-900" :title="currentCsv.original_filename">
              {{ currentCsv.original_filename || 'Unknown file' }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Respondents</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">
              n = {{ currentCsv.respondent_count ?? currentCsv.valid_row_count ?? 0 }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Imported</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">
              {{ formatDate(currentCsv.imported_at || currentCsv.processing_finished_at || currentCsv.created_at) }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Survey template</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">
              Vendor post-event · {{ currentCsv.schema_version || 'v1' }}
            </dd>
          </div>
        </dl>

        <div class="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="busy || !eventId"
            @click="startReplacePick"
          >
            Replace CSV
          </button>
          <button
            type="button"
            class="ml-btn-ghost text-sm text-rose-700 hover:bg-rose-50"
            :disabled="busy || !eventId"
            @click="confirmDelete"
          >
            Delete CSV Data
          </button>
        </div>
      </template>

      <div
        v-else
        class="mt-4 rounded-xl border border-dashed border-sky-200 bg-sky-50/40 px-4 py-6 text-center"
        data-testid="csv-onboarding"
        @dragover.prevent
        @drop.prevent="onDrop"
      >
        <p class="text-sm font-semibold text-ink-900">No CSV data is connected to this event.</p>
        <p class="mt-1 text-xs text-ink-500">
          Supported file: CSV · Expected template: Vendor post-event survey
        </p>
        <p class="mt-2 text-xs text-ink-500">
          Choose a file to validate first. Nothing is imported until you confirm.
        </p>
        <button
          type="button"
          class="ml-btn-primary mt-4 text-sm"
          :disabled="busy || !eventId"
          @click="fileInput?.click()"
        >
          Choose CSV
        </button>
      </div>

      <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
        {{ error }}
      </p>
      <p v-if="info" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
        {{ info }}
      </p>

      <div v-if="importSuccess" class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="ml-btn-primary text-sm" @click="$emit('view-survey-results')">
          View Survey Results
        </button>
        <button type="button" class="ml-btn-ghost text-sm" @click="importSuccess = false">
          Remain in Data Sources
        </button>
      </div>
    </div>

    <!-- Confirm dialog -->
    <div
      v-if="pending"
      class="fixed inset-0 z-40 flex items-center justify-center bg-ink-900/40 p-4"
      data-testid="data-source-confirm"
    >
      <div class="w-full max-w-md rounded-2xl border border-ink-100 bg-white p-4 shadow-lg">
        <p class="text-sm font-extrabold text-ink-900">{{ pending.title }}</p>
        <p class="mt-2 whitespace-pre-line text-sm text-ink-600">{{ pending.body }}</p>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
          <button type="button" class="ml-btn-ghost text-sm" :disabled="busy" @click="cancelPending">
            Cancel
          </button>
          <button
            type="button"
            class="text-sm"
            :class="pending.destructive ? 'ml-btn-primary bg-rose-600 hover:bg-rose-700' : 'ml-btn-primary'"
            :disabled="busy"
            @click="runPending"
          >
            {{ busy ? 'Working…' : (pending.confirmLabel || 'Confirm') }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import {
  deleteCurrentSurveyCsv,
  setAnalyticsSourceMode,
  uploadSurveyImport,
} from '../../services/eventAnalyticsApi';

const props = defineProps({
  eventId: { type: [String, Number], default: '' },
  eventTitle: { type: String, default: '' },
  overview: { type: Object, default: null },
});

const emit = defineEmits(['updated', 'view-survey-results']);

const toast = useToast();
const busy = ref(false);
const error = ref('');
const info = ref('');
const pending = ref(null);
const localMode = ref('system_only');
const fileInput = ref(null);
const pendingFile = ref(null);
const importSuccess = ref(false);
const uploadLocked = ref(false);

const modeOptions = [
  {
    value: 'system_only',
    label: 'System Data',
    hint: 'Bookings, payments, spaces and reservations only.',
  },
  {
    value: 'combined',
    label: 'System + Survey CSV',
    hint: 'Combine operational records with the active survey CSV.',
  },
  {
    value: 'csv_only',
    label: 'Survey CSV Only',
    hint: 'Show survey results only. System Data stays stored but hidden.',
  },
];

const selectedMode = computed(() =>
  localMode.value || props.overview?.analytics_source_mode || 'system_only',
);

const systemIncluded = computed(() =>
  selectedMode.value === 'system_only' || selectedMode.value === 'combined',
);

const currentCsv = computed(() => {
  const latest = props.overview?.data_readiness?.latest_import;
  const surveyReady = props.overview?.survey?.status === 'ready';
  if (surveyReady && latest?.original_filename) {
    return {
      ...latest,
      respondent_count: props.overview?.survey?.respondent_count ?? latest.valid_row_count,
      imported_at: latest.processing_finished_at || latest.created_at,
    };
  }

  const csvSource = (props.overview?.data_sources || []).find(
    (s) => s.type === 'csv_import' && s.included_in_analytics !== false && s.original_filename,
  );
  if (!csvSource) return null;

  return {
    original_filename: csvSource.original_filename,
    respondent_count: csvSource.respondent_count,
    imported_at: csvSource.imported_at,
    schema_version: csvSource.schema_version,
  };
});

watch(
  () => props.overview?.analytics_source_mode,
  (mode) => {
    if (mode) localMode.value = mode;
  },
  { immediate: true },
);

const formatDate = (value) => {
  if (!value) return 'Not available';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const changeMode = async (mode) => {
  if (!props.eventId || mode === selectedMode.value) return;
  busy.value = true;
  error.value = '';
  try {
    const { data } = await setAnalyticsSourceMode(props.eventId, mode);
    localMode.value = data?.analytics_source_mode || mode;
    toast.success('Analytics source updated.');
    emit('updated', data?.overview || null);
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to update analytics source.';
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
};

const startReplacePick = () => {
  error.value = '';
  info.value = '';
  importSuccess.value = false;
  fileInput.value?.click();
};

const confirmDelete = () => {
  error.value = '';
  info.value = '';
  const filename = currentCsv.value?.original_filename || 'the current CSV';
  const n = currentCsv.value?.respondent_count ?? currentCsv.value?.valid_row_count ?? 0;
  const eventName = props.eventTitle || 'this event';
  pending.value = {
    action: 'delete',
    destructive: true,
    confirmLabel: 'Delete CSV Data',
    title: 'Delete CSV Data?',
    body: [
      `Event: ${eventName}`,
      `File: ${filename}`,
      `Respondents: ${n}`,
      '',
      'This permanently deletes the CSV-imported survey responses and uploaded file.',
      'System Data is kept. This cannot be undone.',
    ].join('\n'),
  };
};

const cancelPending = () => {
  pending.value = null;
  pendingFile.value = null;
};

const runPending = async () => {
  if (!pending.value || !props.eventId) return;
  const { action } = pending.value;

  if (action === 'replace_upload' && pendingFile.value) {
    const file = pendingFile.value;
    pending.value = null;
    pendingFile.value = null;
    await uploadFile(file, true);
    return;
  }

  if (action === 'delete') {
    busy.value = true;
    error.value = '';
    try {
      const { data } = await deleteCurrentSurveyCsv(props.eventId);
      pending.value = null;
      importSuccess.value = false;
      info.value = data?.message || 'CSV survey data permanently deleted.';
      localMode.value = data?.analytics_source_mode || 'system_only';
      toast.success(info.value);
      emit('updated', data?.overview || null);
    } catch (e) {
      error.value = e.response?.data?.message || 'Unable to delete CSV survey data.';
      toast.error(error.value);
    } finally {
      busy.value = false;
    }
  }
};

const onDrop = (event) => {
  const file = event.dataTransfer?.files?.[0];
  if (file) handleSelectedFile(file);
};

const onFileChange = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  await handleSelectedFile(file);
};

const handleSelectedFile = async (file) => {
  if (!props.eventId || uploadLocked.value) return;
  error.value = '';
  info.value = '';
  importSuccess.value = false;

  if (currentCsv.value) {
    pendingFile.value = file;
    pending.value = {
      action: 'replace_upload',
      destructive: true,
      confirmLabel: 'Replace CSV',
      title: 'Replace CSV?',
      body: 'This will permanently replace the current CSV and its responses. This cannot be undone.',
    };
    return;
  }

  await uploadFile(file, false);
};

const uploadFile = async (file, replaceExisting) => {
  if (uploadLocked.value) return;
  uploadLocked.value = true;
  busy.value = true;
  error.value = '';
  info.value = '';
  try {
    const { data } = await uploadSurveyImport(props.eventId, file, { replaceExisting });
    info.value = replaceExisting ? 'Survey CSV replaced.' : 'Survey CSV imported.';
    importSuccess.value = true;
    toast.success(info.value);
    emit('updated', data?.overview || null);
  } catch (e) {
    const payload = e.response?.data || {};
    if (e.response?.status === 409 && payload.code === 'survey_import_duplicate') {
      info.value = 'This CSV is already the active survey for this event. No duplicate responses were added.';
      toast.info(info.value);
      return;
    }
    if (e.response?.status === 409 && payload.code === 'survey_import_replace_required') {
      pendingFile.value = file;
      pending.value = {
        action: 'replace_upload',
        destructive: true,
        confirmLabel: 'Replace CSV',
        title: 'Replace CSV?',
        body: 'This will permanently replace the current CSV and its responses. This cannot be undone.',
      };
      return;
    }
    error.value = payload.message || 'Survey import failed. The previous dataset was left unchanged.';
    toast.error(error.value);
  } finally {
    busy.value = false;
    uploadLocked.value = false;
  }
};
</script>
