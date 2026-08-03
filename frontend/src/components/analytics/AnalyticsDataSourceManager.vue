<template>
  <section class="space-y-4" data-testid="analytics-data-source-manager">
    <div class="rounded-xl border border-sky-100 bg-white p-4">
      <h3 class="text-sm font-extrabold text-ink-900">Data Source Manager</h3>
      <p class="mt-1 text-sm text-ink-500">
        Choose which sources Analytics Hub and event reports use. System records are never deleted.
      </p>

      <div class="mt-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Analytics source</p>
        <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Analytics source mode">
          <button
            v-for="opt in modeOptions"
            :key="opt.value"
            type="button"
            class="rounded-lg border px-3 py-1.5 text-sm font-semibold transition"
            :class="selectedMode === opt.value
              ? 'border-brand-600 bg-brand-600 text-white'
              : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300'"
            :disabled="busy || !eventId"
            @click="changeMode(opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>
        <p class="mt-2 text-xs text-ink-500">{{ modeHint }}</p>
      </div>
    </div>

    <div class="rounded-xl border border-sky-100 bg-white p-4" data-testid="current-csv-source">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="text-sm font-extrabold text-ink-900">Current CSV Source</h3>
          <p class="mt-1 text-sm text-ink-500">
            Active vendor survey file used for analytics when CSV is included.
          </p>
        </div>
        <input
          ref="fileInput"
          type="file"
          accept=".csv,text/csv"
          class="hidden"
          :disabled="!eventId || busy"
          @change="onFileChange"
        />
      </div>

      <template v-if="currentCsv">
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Filename</dt>
            <dd class="mt-0.5 truncate font-semibold text-ink-900" :title="currentCsv.original_filename">
              {{ currentCsv.original_filename || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Respondents</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">
              n = {{ currentCsv.respondent_count ?? currentCsv.valid_row_count ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Imported</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">{{ formatDate(currentCsv.imported_at || currentCsv.processing_finished_at || currentCsv.created_at) }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Schema</dt>
            <dd class="mt-0.5 font-semibold text-ink-900">{{ currentCsv.schema_version || '—' }}</dd>
          </div>
        </dl>

        <div class="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="busy || !eventId"
            @click="openReplace"
          >
            Replace CSV
          </button>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="busy || !eventId"
            @click="confirmRemove"
          >
            Remove CSV from Analytics
          </button>
        </div>
      </template>

      <div v-else class="mt-4">
        <p class="text-sm text-ink-500">No active CSV survey is included in analytics for this event.</p>
        <button
          type="button"
          class="ml-btn-primary mt-3 text-sm"
          :disabled="busy || !eventId"
          @click="openReplace"
        >
          Upload CSV
        </button>
      </div>

      <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
        {{ error }}
      </p>
      <p v-if="info" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
        {{ info }}
      </p>
    </div>

    <div
      v-if="pending"
      class="fixed inset-0 z-40 flex items-center justify-center bg-ink-900/40 p-4"
      data-testid="data-source-confirm"
    >
      <div class="w-full max-w-md rounded-2xl border border-ink-100 bg-white p-4 shadow-lg">
        <p class="text-sm font-extrabold text-ink-900">{{ pending.title }}</p>
        <p class="mt-2 text-sm text-ink-600">{{ pending.body }}</p>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
          <button type="button" class="ml-btn-ghost text-sm" :disabled="busy" @click="pending = null">
            Cancel
          </button>
          <button type="button" class="ml-btn-primary text-sm" :disabled="busy" @click="runPending">
            {{ busy ? 'Working…' : 'Confirm' }}
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
  removeCsvFromAnalytics,
  setAnalyticsSourceMode,
  uploadSurveyImport,
} from '../../services/eventAnalyticsApi';

const props = defineProps({
  eventId: { type: [String, Number], default: '' },
  overview: { type: Object, default: null },
});

const emit = defineEmits(['updated']);

const toast = useToast();
const busy = ref(false);
const error = ref('');
const info = ref('');
const pending = ref(null);
const localMode = ref('combined');
const fileInput = ref(null);
const awaitingReplaceConfirm = ref(false);
const pendingFile = ref(null);

const modeOptions = [
  { value: 'combined', label: 'Combined' },
  { value: 'system_only', label: 'System only' },
  { value: 'csv_only', label: 'CSV only' },
];

const selectedMode = computed(() =>
  localMode.value || props.overview?.analytics_source_mode || 'combined',
);

const currentCsv = computed(() => {
  const latest = props.overview?.data_readiness?.latest_import;
  if (latest?.original_filename) {
    return {
      ...latest,
      respondent_count: props.overview?.survey?.respondent_count ?? latest.valid_row_count,
      imported_at: latest.processing_finished_at || latest.created_at,
    };
  }

  const csvSource = (props.overview?.data_sources || []).find(
    (s) => s.type === 'csv_import' && s.included_in_analytics !== false,
  );
  if (!csvSource) return null;

  return {
    original_filename: csvSource.original_filename,
    respondent_count: csvSource.respondent_count,
    imported_at: csvSource.imported_at,
    schema_version: csvSource.schema_version,
  };
});

const modeHint = computed(() => {
  switch (selectedMode.value) {
    case 'system_only':
      return 'Hub and reports use System Data only. Survey respondents are excluded (not deleted).';
    case 'csv_only':
      return 'Hub and reports use the active CSV survey only. System Data is excluded (not deleted).';
    default:
      return 'Hub and reports combine System Data with the active CSV survey. Survey n never includes bookings or invoices.';
  }
});

watch(
  () => props.overview?.analytics_source_mode,
  (mode) => {
    if (mode) localMode.value = mode;
  },
  { immediate: true },
);

const formatDate = (value) => {
  if (!value) return '—';
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
    toast.success('Analytics source mode updated.');
    emit('updated', data?.overview || null);
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to update analytics source mode.';
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
};

const openReplace = () => {
  error.value = '';
  info.value = '';
  if (currentCsv.value) {
    pending.value = {
      action: 'replace_pick',
      title: 'Replace CSV?',
      body: 'A survey dataset already exists for this event. Replace it with a newly validated file? The previous file is kept for duplicate protection.',
    };
    return;
  }
  fileInput.value?.click();
};

const confirmRemove = () => {
  error.value = '';
  info.value = '';
  pending.value = {
    action: 'remove',
    title: 'Remove CSV from analytics?',
    body: 'The active CSV will be excluded from Analytics Hub and reports. Analytics mode will switch to System only. The raw file and import metadata are kept.',
  };
};

const runPending = async () => {
  if (!pending.value || !props.eventId) return;
  const { action } = pending.value;

  if (action === 'replace_pick') {
    pending.value = null;
    awaitingReplaceConfirm.value = true;
    fileInput.value?.click();
    return;
  }

  if (action === 'replace_upload' && pendingFile.value) {
    await uploadFile(pendingFile.value, true);
    pending.value = null;
    pendingFile.value = null;
    return;
  }

  if (action === 'remove') {
    busy.value = true;
    error.value = '';
    try {
      const { data } = await removeCsvFromAnalytics(props.eventId);
      pending.value = null;
      info.value = data?.message || 'CSV removed from analytics.';
      localMode.value = data?.analytics_source_mode || 'system_only';
      toast.success(info.value);
      emit('updated', data?.overview || null);
    } catch (e) {
      error.value = e.response?.data?.message || 'Unable to remove CSV from analytics.';
      toast.error(error.value);
    } finally {
      busy.value = false;
    }
  }
};

const onFileChange = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !props.eventId) return;

  if (currentCsv.value && !awaitingReplaceConfirm.value) {
    pendingFile.value = file;
    pending.value = {
      action: 'replace_upload',
      title: 'Replace CSV?',
      body: 'A survey dataset already exists for this event. Replace it with the newly selected file?',
    };
    return;
  }

  awaitingReplaceConfirm.value = false;
  await uploadFile(file, Boolean(currentCsv.value));
};

const uploadFile = async (file, replaceExisting) => {
  busy.value = true;
  error.value = '';
  info.value = '';
  try {
    const { data } = await uploadSurveyImport(props.eventId, file, { replaceExisting });
    info.value = replaceExisting ? 'Survey dataset replaced.' : 'Survey import completed.';
    toast.success(info.value);
    emit('updated', data?.overview || null);
  } catch (e) {
    const payload = e.response?.data || {};
    if (e.response?.status === 409 && payload.code === 'survey_import_duplicate') {
      info.value = 'This CSV has already been imported for this event. No duplicate responses were added.';
      toast.info(info.value);
      return;
    }
    if (e.response?.status === 409 && payload.code === 'survey_import_replace_required') {
      pendingFile.value = file;
      pending.value = {
        action: 'replace_upload',
        title: 'Replace CSV?',
        body: 'A survey dataset already exists for this event. Replace it with the newly validated file?',
      };
      return;
    }
    error.value = payload.message || 'Survey import failed.';
    toast.error(error.value);
  } finally {
    busy.value = false;
    awaitingReplaceConfirm.value = false;
  }
};
</script>
