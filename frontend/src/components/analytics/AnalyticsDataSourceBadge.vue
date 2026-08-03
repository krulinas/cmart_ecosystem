<template>
  <div class="flex flex-wrap items-start gap-2" data-testid="analytics-data-sources">
    <div
      v-if="!displaySources.length"
      class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-ink-50 px-2.5 py-1 text-xs font-semibold text-ink-600"
    >
      <span class="h-1.5 w-1.5 rounded-full bg-ink-400" />
      No Data
    </div>

    <template v-else>
      <div
        v-if="mode === 'mixed'"
        class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-950"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-teal-600" />
        Mixed Sources
      </div>

      <article
        v-for="(source, idx) in displaySources"
        :key="`${source.type}-${source.batch_id || idx}`"
        class="rounded-lg border px-2.5 py-1.5 text-xs"
        :class="sourceCardClass(source)"
      >
        <p class="font-bold">
          {{ source.type === 'csv_import' ? 'CSV Import' : 'System Data' }}
        </p>
        <template v-if="source.type === 'csv_import'">
          <p class="mt-0.5 truncate font-medium" :title="source.original_filename">
            {{ source.original_filename || 'Survey file' }}
          </p>
          <p class="mt-0.5 text-[11px] opacity-90">
            Batch #{{ source.batch_id }}
            <template v-if="source.imported_at"> · {{ formatDate(source.imported_at) }}</template>
            <template v-if="source.respondent_count != null"> · n = {{ source.respondent_count }}</template>
            <template v-if="source.schema_version"> · {{ source.schema_name || 'schema' }} v{{ source.schema_version }}</template>
          </p>
        </template>
        <template v-else>
          <p class="mt-0.5 font-medium">System Database</p>
          <p class="mt-0.5 text-[11px] opacity-90">
            <template v-if="source.updated_at">Updated {{ formatDate(source.updated_at) }}</template>
            <template v-if="(source.sources || []).length">
              · {{ humanSources(source.sources) }}
            </template>
          </p>
        </template>
        <p
          class="mt-1 text-[10px] font-bold uppercase tracking-wide"
          :class="source.included_in_analytics ? 'text-emerald-700' : 'text-ink-500'"
        >
          {{ source.inclusion_label
            || (source.included_in_analytics ? 'Included in analytics' : 'Excluded from analytics') }}
        </p>
      </article>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  sources: { type: Array, default: () => [] },
  /** all | system | csv — which source types to show */
  filter: { type: String, default: 'all' },
});

const filtered = computed(() => {
  const list = Array.isArray(props.sources) ? props.sources : [];
  if (props.filter === 'system') return list.filter((s) => s.type === 'system_database');
  if (props.filter === 'csv') return list.filter((s) => s.type === 'csv_import');
  return list;
});

/** Prefer showing included sources; fall back to all filtered for provenance. */
const displaySources = computed(() => {
  const list = filtered.value;
  const included = list.filter((s) => s.included_in_analytics !== false);
  if (props.filter === 'all') {
    // Overview: show Mixed based on included; still list cards that are present.
    return list.filter((s) => {
      if (s.type === 'csv_import' && !s.original_filename && s.included_in_analytics === false) {
        return false;
      }
      return true;
    });
  }
  return included.length ? included : list;
});

const mode = computed(() => {
  const included = displaySources.value.filter((s) => s.included_in_analytics !== false);
  const types = new Set(included.map((s) => s.type));
  if (types.has('system_database') && types.has('csv_import')) return 'mixed';
  if (types.has('csv_import')) return 'csv';
  if (types.has('system_database')) return 'system';
  return 'none';
});

const sourceCardClass = (source) => {
  const included = source.included_in_analytics !== false;
  if (source.type === 'csv_import') {
    return included
      ? 'border-amber-200 bg-amber-50 text-amber-950'
      : 'border-ink-200 bg-ink-50 text-ink-600 opacity-80';
  }
  return included
    ? 'border-sky-200 bg-sky-50 text-sky-950'
    : 'border-ink-200 bg-ink-50 text-ink-600 opacity-80';
};

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const humanSources = (list) => (list || [])
  .map((s) => String(s).replace(/_/g, ' '))
  .join(', ');
</script>
