<template>
  <div class="rounded-xl border border-sky-100 bg-white p-3" :data-testid="testId">
    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
      </div>
    </div>

    <p v-if="!canShowTrend" class="py-6 text-center text-sm text-ink-500">{{ emptyText || insufficientText }}</p>
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
import { destroyChartInstance } from '../../utils/chartLifecycle';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  /**
   * Points: { label, value (nullable), event_id?, selected? }.
   * Null values are skipped. A single valid point never draws a line.
   */
  points: { type: Array, default: () => [] },
  selectedEventId: { type: [Number, String], default: null },
  color: { type: String, default: ANALYTICS_PALETTE.primary },
  emptyText: { type: String, default: '' },
  insufficientText: { type: String, default: 'Need at least two events with valid values to show a trend.' },
  testId: { type: String, default: 'analytics-trend-chart' },
  chartHeight: { type: Number, default: 220 },
});

const canvasRef = ref(null);
let chart = null;

const validPoints = computed(() => {
  const list = Array.isArray(props.points) ? props.points : [];
  return list
    .map((p) => {
      if (p == null || p.value == null || p.value === '') return null;
      const n = Number(p.value);
      if (Number.isNaN(n)) return null;
      return {
        label: String(p.label ?? p.event_name ?? p.event_id ?? ''),
        value: n,
        eventId: p.event_id != null ? String(p.event_id) : null,
        selected: Boolean(p.selected)
          || (props.selectedEventId != null && String(p.event_id) === String(props.selectedEventId)),
      };
    })
    .filter(Boolean);
});

/** Never render a meaningless one-point trend. */
const canShowTrend = computed(() => validPoints.value.length >= 2);

const ariaLabel = computed(() => {
  const parts = validPoints.value.map((p) => `${p.label}: ${p.value}`);
  return `${props.title}. ${parts.join('. ')}`;
});

const destroyChart = () => {
  chart = destroyChartInstance(chart);
};

const renderChart = () => {
  destroyChart();
  if (!canvasRef.value || !canShowTrend.value) return;

  const pointColors = validPoints.value.map((p) => (
    p.selected ? ANALYTICS_PALETTE.highlight : props.color
  ));
  const pointRadii = validPoints.value.map((p) => (p.selected ? 6 : 3));

  chart = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      labels: validPoints.value.map((p) => p.label),
      datasets: [{
        label: props.title,
        data: validPoints.value.map((p) => p.value),
        borderColor: props.color,
        backgroundColor: withAlpha(props.color, 0.18),
        fill: true,
        tension: 0.25,
        pointBackgroundColor: pointColors,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: pointRadii,
        pointHoverRadius: 7,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: chartAnimationOption(validPoints.value.length),
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: ANALYTICS_PALETTE.muted, maxRotation: 35 },
        },
        y: {
          beginAtZero: true,
          grid: { color: ANALYTICS_PALETTE.grid },
          ticks: { color: ANALYTICS_PALETTE.muted },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const row = validPoints.value[ctx.dataIndex];
              const mark = row?.selected ? ' (selected event)' : '';
              return `${ctx.parsed.y}${mark}`;
            },
          },
        },
      },
    },
  });
};

onMounted(renderChart);
watch(() => [props.points, props.selectedEventId, props.color], () => renderChart(), { deep: true });
onBeforeUnmount(destroyChart);
</script>
