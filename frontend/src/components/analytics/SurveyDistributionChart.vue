<template>
  <div class="rounded-xl border border-sky-100 bg-white p-3" :data-testid="testId">
    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
      </div>
      <p v-if="denominator != null" class="text-xs font-semibold text-ink-500">n = {{ denominator }}</p>
    </div>

    <p v-if="!hasData" class="py-6 text-center text-sm text-ink-500">{{ emptyText }}</p>
    <div v-else class="relative" :style="{ height: `${chartHeight}px` }">
      <canvas ref="canvasRef" role="img" :aria-label="title" tabindex="0" />
    </div>

    <p
      v-if="selectionNote"
      class="mt-2 rounded-lg border border-teal-100 bg-teal-50/70 px-2.5 py-1.5 text-xs text-teal-950"
      data-testid="chart-selection-note"
    >
      {{ selectionNote }}
    </p>

    <p
      v-if="unansweredNote"
      class="mt-2 text-xs text-ink-500"
    >
      {{ unansweredNote }}
    </p>

    <ul v-if="legendItems.length" class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-ink-600">
      <li v-for="item in legendItems" :key="item.key" class="inline-flex items-center gap-1.5">
        <span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: item.color }" />
        <span>{{ item.label }} · {{ item.count }} ({{ item.percent }}%)</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { humanizeAnalyticsLabel } from '../../utils/analyticsLabels';
import {
  CHART_COLORS,
  formatTooltip,
  metricValue,
  prepareSurveyRows,
  SHORT_LABELS,
} from '../../utils/surveyChartConfig';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  order: { type: Array, default: () => [] },
  denominator: { type: Number, default: null },
  metricMode: { type: String, default: 'count' },
  chartType: { type: String, required: true },
  includeZeros: { type: Boolean, default: false },
  sortByCount: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No responses for this question yet.' },
  testId: { type: String, default: 'survey-distribution-chart' },
  unansweredCount: { type: Number, default: 0 },
  answeredCount: { type: Number, default: null },
});

const emit = defineEmits(['select']);

const canvasRef = ref(null);
const selectedKey = ref(null);
let chart;

const prepared = computed(() => prepareSurveyRows(props.rows, props.order, {
  includeZeros: props.includeZeros,
  sortByCount: props.sortByCount,
}));

const hasData = computed(() => prepared.value.some((r) => r.count > 0)
  || (props.includeZeros && prepared.value.length > 0));

const chartHeight = computed(() => {
  if (props.chartType === 'column-v') return 240;
  if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') return 110;
  return Math.max(168, prepared.value.length * 38);
});

const displayLabel = (row) => SHORT_LABELS[row.key]
  || SHORT_LABELS[row.label]
  || humanizeAnalyticsLabel(row.key || row.label);

const selectionNote = computed(() => {
  if (!selectedKey.value) return '';
  const row = prepared.value.find((r) => r.key === selectedKey.value);
  if (!row) return '';
  const label = displayLabel(row);
  if (props.chartType === 'stacked-h') {
    const preparedAnswered = prepared.value.reduce(
      (sum, r) => sum + r.count,
      0,
    );
    const answered = props.answeredCount ?? preparedAnswered;
    const share = answered ? Math.round((row.count / answered) * 1000) / 10 : 0;
    return `${label}: ${row.count} of ${answered} answered (${share}%).`;
  }
  return `${label}: ${formatTooltip(row, props.metricMode)}`;
});

const unansweredNote = computed(() => {
  if (!props.unansweredCount || props.unansweredCount <= 0) return '';
  const n = props.denominator || 0;
  return `Unanswered: ${props.unansweredCount} of ${n} respondents (shown separately from category totals).`;
});

const legendItems = computed(() => {
  if (props.chartType !== 'stacked-h' && props.chartType !== 'segmented-h') return [];
  const preparedAnswered = prepared.value.reduce(
    (sum, row) => sum + row.count,
    0,
  );
  const answered =
    props.answeredCount ??
    (preparedAnswered || props.denominator || 0);
  return prepared.value.filter((r) => r.count > 0 || props.includeZeros).map((r, idx) => ({
    key: r.key,
    label: displayLabel(r),
    count: r.count,
    percent: answered ? Math.round((r.count / answered) * 1000) / 10 : 0,
    color: CHART_COLORS.stack[idx % CHART_COLORS.stack.length],
  }));
});

const destroyChart = () => {
  chart?.destroy();
  chart = null;
};

const colorFor = (row, idx) => {
  const selected = selectedKey.value === row.key;
  if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') {
    return selected
      ? CHART_COLORS.selected
      : CHART_COLORS.stack[idx % CHART_COLORS.stack.length];
  }
  if (row.count === 0) return CHART_COLORS.zero;
  return selected ? CHART_COLORS.selected : CHART_COLORS.brand;
};

const softColorFor = (row, idx) => {
  const selected = selectedKey.value === row.key;
  if (row.count === 0) return CHART_COLORS.zero;
  if (selected) return CHART_COLORS.selectedSoft;
  if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') {
    return CHART_COLORS.stack[idx % CHART_COLORS.stack.length];
  }
  return CHART_COLORS.brandSoft;
};

const lollipopPlugin = {
  id: 'surveyLollipopDots',
  afterDatasetsDraw(instance) {
    if (props.chartType !== 'lollipop-h') return;
    const meta = instance.getDatasetMeta(0);
    const { ctx } = instance;
    meta.data.forEach((element, index) => {
      const row = prepared.value[index];
      if (!row) return;
      const { x, y } = element.getProps(['x', 'y'], true);
      ctx.save();
      ctx.beginPath();
      ctx.fillStyle = colorFor(row, index);
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2;
      ctx.arc(x, y, selectedKey.value === row.key ? 7 : 5.5, 0, Math.PI * 2);
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    });
  },
};

const toggleSelect = (row) => {
  if (!row) {
    selectedKey.value = null;
    emit('select', null);
  } else {
    selectedKey.value = selectedKey.value === row.key ? null : row.key;
    emit('select', selectedKey.value ? row : null);
  }
  renderChart();
};

const sharedTooltip = {
  callbacks: {
    title: (items) => {
      if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') {
        return items[0]?.dataset?.label || '';
      }
      const idx = items[0]?.dataIndex ?? 0;
      return displayLabel(prepared.value[idx] || {});
    },
    label: (ctx) => {
      if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') {
        const row = prepared.value[ctx.datasetIndex];
        return row ? formatTooltip(row, props.metricMode) : '';
      }
      const row = prepared.value[ctx.dataIndex];
      return row ? formatTooltip(row, props.metricMode) : '';
    },
  },
};

const buildLollipop = () => {
  const labels = prepared.value.map(displayLabel);
  const values = prepared.value.map((r) => metricValue(r, props.metricMode));
  const max = Math.max(...values, 0);

  return {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: props.metricMode === 'percent' ? '%' : 'Count',
        data: values,
        backgroundColor: prepared.value.map((r, i) => softColorFor(r, i)),
        borderColor: prepared.value.map((r, i) => colorFor(r, i)),
        borderWidth: 0,
        barThickness: 3,
        borderSkipped: false,
        borderRadius: 2,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 420, easing: 'easeOutQuart' },
      scales: {
        x: {
          beginAtZero: true,
          suggestedMax: props.metricMode === 'percent' ? 100 : Math.max(1, Math.ceil(max * 1.15)),
          ticks: {
            precision: 0,
            callback: (v) => (props.metricMode === 'percent' ? `${v}%` : v),
          },
          grid: { color: 'rgba(148,163,184,0.18)' },
        },
        y: {
          grid: { display: false },
          ticks: { color: '#334155', font: { size: 11 } },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: sharedTooltip,
      },
      onClick: (_evt, elements) => {
        if (!elements.length) {
          toggleSelect(null);
          return;
        }
        toggleSelect(prepared.value[elements[0].index]);
      },
    },
    plugins: [lollipopPlugin],
  };
};

const buildStacked = () => {
  const preparedAnswered = prepared.value.reduce(
    (sum, row) => sum + row.count,
    0,
  );
  const answered =
    props.answeredCount ??
    (preparedAnswered || 1);
  const datasets = prepared.value.map((row, idx) => {
    const value = props.metricMode === 'percent'
      ? (answered ? (row.count / answered) * 100 : 0)
      : row.count;
    return {
      label: displayLabel(row),
      data: [value],
      backgroundColor: softColorFor(row, idx),
      borderColor: colorFor(row, idx),
      borderWidth: selectedKey.value === row.key ? 2 : 0,
      barThickness: 32,
      borderRadius: 4,
    };
  });

  return {
    type: 'bar',
    data: { labels: [' '], datasets },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 420, easing: 'easeOutQuart' },
      scales: {
        x: {
          stacked: true,
          max: props.metricMode === 'percent' ? 100 : answered,
          beginAtZero: true,
          ticks: {
            callback: (v) => (props.metricMode === 'percent' ? `${v}%` : v),
          },
          grid: { color: 'rgba(148,163,184,0.18)' },
        },
        y: { stacked: true, display: false },
      },
      plugins: {
        legend: { display: false },
        tooltip: sharedTooltip,
      },
      onClick: (_evt, elements) => {
        if (!elements.length) {
          toggleSelect(null);
          return;
        }
        toggleSelect(prepared.value[elements[0].datasetIndex]);
      },
    },
  };
};

const buildColumns = () => {
  const labels = prepared.value.map(displayLabel);
  const values = prepared.value.map((r) => metricValue(r, props.metricMode));

  return {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: props.metricMode === 'percent' ? '%' : 'Count',
        data: values,
        backgroundColor: prepared.value.map((r, i) => softColorFor(r, i)),
        borderColor: prepared.value.map((r, i) => colorFor(r, i)),
        borderWidth: 1,
        borderRadius: 6,
        maxBarThickness: 52,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 420, easing: 'easeOutQuart' },
      scales: {
        x: {
          ticks: {
            maxRotation: 40,
            minRotation: 0,
            autoSkip: false,
            font: { size: 10 },
            color: '#334155',
          },
          grid: { display: false },
        },
        y: {
          beginAtZero: true,
          suggestedMax: props.metricMode === 'percent' ? 100 : undefined,
          ticks: {
            precision: 0,
            callback: (v) => (props.metricMode === 'percent' ? `${v}%` : v),
          },
          grid: { color: 'rgba(148,163,184,0.18)' },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: sharedTooltip,
      },
      onClick: (_evt, elements) => {
        if (!elements.length) {
          toggleSelect(null);
          return;
        }
        toggleSelect(prepared.value[elements[0].index]);
      },
    },
  };
};

const renderChart = () => {
  if (!canvasRef.value || !hasData.value) {
    destroyChart();
    return;
  }

  let config;
  if (props.chartType === 'lollipop-h') config = buildLollipop();
  else if (props.chartType === 'stacked-h' || props.chartType === 'segmented-h') config = buildStacked();
  else config = buildColumns();

  destroyChart();
  chart = new Chart(canvasRef.value, config);
};

watch(
  () => [props.rows, props.metricMode, props.denominator, props.unansweredCount, props.answeredCount, prepared.value],
  () => renderChart(),
  { deep: true },
);

onMounted(renderChart);
onBeforeUnmount(destroyChart);
</script>
