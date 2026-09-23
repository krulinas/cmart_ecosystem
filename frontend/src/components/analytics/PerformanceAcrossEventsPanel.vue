<template>
  <section class="rounded-xl border border-sky-100 bg-white p-3" data-testid="performance-across-events">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.benchmark.title') }}</h3>
        <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.benchmark.hint') }}</p>
      </div>
      <label v-if="available && hasAnyTrend" class="block text-xs">
        <span class="mb-1 block font-semibold uppercase tracking-wide text-ink-500">
          {{ t('organizer.analytics.benchmark.metric') }}
        </span>
        <select v-model="metricKey" class="ml-input text-sm">
          <option v-for="opt in metricOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
        </select>
      </label>
    </div>

    <p v-if="loading" class="mt-3 py-6 text-center text-sm text-ink-500">
      {{ t('organizer.analytics.benchmark.loading') }}
    </p>

    <div
      v-else-if="!available"
      class="mt-3 rounded-lg border border-dashed border-ink-200 bg-ink-50/50 px-3 py-6 text-center text-sm text-ink-600"
      data-testid="benchmark-unavailable"
    >
      {{ message || t('organizer.analytics.benchmark.unavailable') }}
    </div>

    <template v-else>
      <p
        v-if="comparisonText"
        class="mt-3 rounded-lg border border-teal-100 bg-teal-50/70 px-3 py-2 text-sm text-teal-950"
        data-testid="benchmark-comparison"
      >
        {{ comparisonText }}
      </p>

      <p
        v-else-if="insufficientMedian"
        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        data-testid="benchmark-insufficient"
      >
        {{ t('organizer.analytics.benchmark.insufficientHistory', { n: sampleSize }) }}
      </p>

      <AnalyticsTrendChart
        class="mt-3"
        :title="selectedMetricLabel"
        :subtitle="t('organizer.analytics.benchmark.trendHint')"
        :points="trendPoints"
        :selected-event-id="selectedEventId"
        :color="palette.primary"
        :empty-text="t('organizer.analytics.benchmark.noTrend')"
        :insufficient-text="t('organizer.analytics.benchmark.needTwoPoints')"
        test-id="benchmark-trend-chart"
      />

      <p class="mt-2 text-[11px] text-ink-500">
        {{ t('organizer.analytics.benchmark.sampleSize', { n: sampleSize }) }}
        · {{ t('organizer.analytics.benchmark.pythonPowered') }}
      </p>
    </template>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalyticsTrendChart from './AnalyticsTrendChart.vue';
import { ANALYTICS_PALETTE } from '../../utils/analyticsChartPalette';
import { getEventAnalyticsBenchmark } from '../../services/eventAnalyticsApi';

const props = defineProps({
  eventId: { type: [Number, String], default: null },
});

const { t } = useI18n();
const palette = ANALYTICS_PALETTE;

const loading = ref(false);
const available = ref(false);
const message = ref('');
const sampleSize = ref(0);
const metrics = ref({});
const trends = ref([]);
const warnings = ref([]);
const selectedEventId = ref(null);
const metricKey = ref('site_utilisation_percent');

const metricOptions = computed(() => [
  { id: 'approved_vendors', label: t('organizer.analytics.benchmark.metricApprovedVendors') },
  { id: 'site_utilisation_percent', label: t('organizer.analytics.benchmark.metricUtilisation') },
  { id: 'collection_rate_percent', label: t('organizer.analytics.benchmark.metricCollection') },
  { id: 'average_rating', label: t('organizer.analytics.benchmark.metricRating') },
]);

const selectedMetricLabel = computed(() =>
  metricOptions.value.find((o) => o.id === metricKey.value)?.label || metricKey.value,
);

const metricBlock = computed(() => metrics.value?.[metricKey.value] || null);

const insufficientMedian = computed(() =>
  warnings.value.includes('insufficient_history_for_median_comparison')
  || (metricBlock.value?.warning === 'insufficient_history_for_median')
  || (sampleSize.value > 0 && sampleSize.value < 3),
);

const comparisonText = computed(() => {
  const block = metricBlock.value;
  if (!block || block.value == null || block.median == null || block.delta_from_median == null) {
    return '';
  }
  const delta = Number(block.delta_from_median);
  const abs = Math.abs(delta);
  const rounded = Number.isInteger(abs) ? String(abs) : abs.toFixed(1);
  if (delta === 0) {
    return t('organizer.analytics.benchmark.atMedian', { metric: selectedMetricLabel.value });
  }
  const direction = delta > 0
    ? t('organizer.analytics.benchmark.above')
    : t('organizer.analytics.benchmark.below');
  return t('organizer.analytics.benchmark.deltaFromMedian', {
    metric: selectedMetricLabel.value,
    delta: rounded,
    direction,
  });
});

const trendForMetric = computed(() =>
  (trends.value || []).find((tr) => tr.metric === metricKey.value) || null,
);

const hasAnyTrend = computed(() => (trends.value || []).some((tr) => (tr.points || []).length >= 2));

const trendPoints = computed(() => {
  const points = trendForMetric.value?.points || [];
  return points.map((p) => ({
    event_id: p.event_id,
    label: p.event_name || String(p.event_id),
    value: p.value,
    selected: Boolean(p.selected),
  }));
});

const load = async () => {
  if (!props.eventId) {
    available.value = false;
    return;
  }
  loading.value = true;
  message.value = '';
  try {
    const { data } = await getEventAnalyticsBenchmark(props.eventId);
    available.value = data?.available !== false && data?.status !== 'unavailable';
    message.value = data?.message || '';
    sampleSize.value = Number(data?.sample_size || 0);
    metrics.value = data?.metrics || {};
    trends.value = Array.isArray(data?.trends) ? data.trends : [];
    warnings.value = Array.isArray(data?.warnings) ? data.warnings : [];
    selectedEventId.value = data?.selected_event_id ?? props.eventId;

    // Prefer a metric that actually has a trend when possible.
    const withTrend = (trends.value || []).find((tr) => (tr.points || []).length >= 2);
    if (withTrend?.metric) {
      metricKey.value = withTrend.metric;
    }
  } catch {
    available.value = false;
    message.value = t('organizer.analytics.benchmark.unavailable');
    metrics.value = {};
    trends.value = [];
    warnings.value = ['python_unreachable'];
  } finally {
    loading.value = false;
  }
};

watch(() => props.eventId, () => {
  load();
}, { immediate: true });
</script>
