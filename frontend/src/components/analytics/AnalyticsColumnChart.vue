<template>
  <div class="rounded-xl border border-sky-100 bg-white p-3" :data-testid="testId">
    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
      </div>
    </div>

    <p v-if="!hasData" class="py-6 text-center text-sm text-ink-500">{{ emptyText || 'No data available.' }}</p>
    <div v-else class="relative" :style="{ height: `${chartHeight}px` }">
      <canvas ref="canvasRef" role="img" :aria-label="ariaLabel" tabindex="0" />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import {
  ANALYTICS_PALETTE,
  chartAnimationOption,
  withAlpha,
} from '../../utils/analyticsChartPalette';
import { destroyChartInstance, presentNumericRows } from '../../utils/chartLifecycle';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  color: { type: String, default: ANALYTICS_PALETTE.survey },
  emptyText: { type: String, default: '' },
  testId: { type: String, default: 'analytics-column-chart' },
  chartHeight: { type: Number, default: 220 },
});

const canvasRef = ref(null);
let chart = null;

const points = computed(() => presentNumericRows(props.rows, 'count'));
const hasData = computed(() => points.value.some((p) => p.count > 0) || points.value.length > 0);
const ariaLabel = computed(() => {
  const parts = points.value.map((p) => `${p.label}: ${p.count}`);
  return `${props.title}. ${parts.join('. ')}`;
});

const destroyChart = () => {
  chart = destroyChartInstance(chart);
};

const renderChart = () => {
  destroyChart();
  if (!canvasRef.value || !hasData.value) return;

  chart = new Chart(canvasRef.value, {
    type: 'bar',
    data: {
      labels: points.value.map((p) => p.label),
      datasets: [{
        label: props.title,
        data: points.value.map((p) => p.count),
        backgroundColor: withAlpha(props.color, 0.75),
        borderColor: props.color,
        borderWidth: 1,
        borderRadius: 4,
        maxBarThickness: 42,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: chartAnimationOption(points.value.length),
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: ANALYTICS_PALETTE.muted, maxRotation: 40, minRotation: 0 },
        },
        y: {
          beginAtZero: true,
          grid: { color: ANALYTICS_PALETTE.grid },
          ticks: { color: ANALYTICS_PALETTE.muted, precision: 0 },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const row = points.value[ctx.dataIndex];
              if (!row) return `${ctx.raw}`;
              const pct = row.percent != null ? ` (${row.percent}%)` : '';
              return `${row.label}: ${row.count}${pct}`;
            },
          },
        },
      },
    },
  });
};

onMounted(renderChart);
watch(() => [props.rows, props.color], () => renderChart(), { deep: true });
onBeforeUnmount(destroyChart);
</script>
