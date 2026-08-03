<template>
  <div
    class="flex flex-wrap items-center gap-1.5"
    data-testid="analytics-data-sources"
    :class="compact ? '' : 'items-start gap-2'"
  >
    <div
      v-if="!displaySources.length"
      class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-600"
      title="No analytics sources available for this event"
    >
      No Data
    </div>

    <template v-else>
      <span
        v-if="mode === 'mixed'"
        class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-0.5 text-[11px] font-semibold text-teal-950"
        title="Overview combines System Data and CSV survey responses"
      >
        Mixed Sources
      </span>

      <span
        v-for="(source, idx) in displaySources"
        :key="`${source.type}-${source.batch_id || idx}`"
        class="inline-flex max-w-full items-center truncate rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
        :class="pillClass(source)"
        :title="tooltipFor(source)"
      >
        <template v-if="source.type === 'csv_import'">
          CSV: {{ source.original_filename || 'survey file' }}
        </template>
        <template v-else>
          System Data
        </template>
        <span
          v-if="source.included_in_analytics === false"
          class="ml-1 font-medium opacity-70"
        >(excluded)</span>
      </span>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  sources: { type: Array, default: () => [] },
  /** all | system | csv */
  filter: { type: String, default: 'all' },
  /** Compact pills (Overview). Non-compact keeps the same compact pills for consistency. */
  compact: { type: Boolean, default: true },
});

const filtered = computed(() => {
  const list = Array.isArray(props.sources) ? props.sources : [];
  if (props.filter === 'system') return list.filter((s) => s.type === 'system_database');
  if (props.filter === 'csv') return list.filter((s) => s.type === 'csv_import');
  return list;
});

const displaySources = computed(() => {
  const list = filtered.value;
  if (props.filter === 'all') {
    return list.filter((s) => {
      if (s.type === 'csv_import' && !s.original_filename && s.included_in_analytics === false) {
        return false;
      }
      return true;
    });
  }
  const included = list.filter((s) => s.included_in_analytics !== false);
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

const pillClass = (source) => {
  const included = source.included_in_analytics !== false;
  if (source.type === 'csv_import') {
    return included
      ? 'border-amber-200 bg-amber-50 text-amber-950'
      : 'border-ink-200 bg-ink-50 text-ink-500';
  }
  return included
    ? 'border-sky-200 bg-sky-50 text-sky-950'
    : 'border-ink-200 bg-ink-50 text-ink-500';
};

const formatDate = (value) => {
  if (!value) return '';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return String(value);
  }
};

const humanSources = (list) => (list || [])
  .map((s) => String(s).replace(/_/g, ' '))
  .join(', ');

const tooltipFor = (source) => {
  if (source.type === 'csv_import') {
    const parts = [
      source.original_filename || 'CSV import',
      source.batch_id != null ? `batch #${source.batch_id}` : null,
      source.respondent_count != null ? `n = ${source.respondent_count}` : null,
      source.schema_version || null,
      source.imported_at ? `imported ${formatDate(source.imported_at)}` : null,
      source.inclusion_label || null,
    ];
    return parts.filter(Boolean).join(' · ');
  }
  const parts = [
    'System Database',
    humanSources(source.sources),
    source.updated_at ? `updated ${formatDate(source.updated_at)}` : null,
    source.inclusion_label || null,
  ];
  return parts.filter(Boolean).join(' · ');
};
</script>
