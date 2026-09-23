<template>
  <div class="rounded-xl border border-sky-100 bg-white p-3" :data-testid="testId">
    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
      </div>
    </div>

    <p v-if="!hasData" class="py-6 text-center text-sm text-ink-500">{{ emptyText || 'No data available.' }}</p>
    <template v-else>
      <div class="relative" :style="{ height: `${chartHeight}px` }">
        <canvas ref="canvasRef" role="img" :aria-label="ariaLabel" tabindex="0" />
      </div>
      <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-ink-600" :aria-label="title">
        <li v-for="(seg, idx) in segments" :key="seg.key || idx" class="inline-flex items-center gap-1.5">
          <span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: colorAt(idx) }" />
          <span>{{ seg.label }} · {{ formatValue(seg.count) }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import {
  ANALYTICS_PALETTE,
  COMPOSITION_COLORS,
  chartAnimationOption,
} from '../../utils/analyticsChartPalette';
import { destroyChartInstance, presentNumericRows } from '../../utils/chartLifecycle';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  /** Named segments for one stacked bar (e.g. collected vs outstanding). */
  segments: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [ANALYTICS_PALETTE.positive, ANALYTICS_PALETTE.warning] },
  emptyText: { type: String, default: '' },
  testId: { type: String, default: 'analytics-stacked-bar-chart' },
  valuePrefix: { type: String, default: '' },
  chartHeight: { type: Number, default: 88 },
});

const canvasRef = ref(null);
let chart = null;

const segments = computed(() => presentNumericRows(props.segments, 'count'));
const hasData = computed(() => segments.value.some((s) => s.count > 0));
const colorAt = (idx) => props.colors[idx % props.colors.length] || COMPOSITION_COLORS[idx % COMPOSITION_COLORS.length];

const formatValue = (n) => {
  if (props.valuePrefix) return `${props.valuePrefix}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  return String(n);
};

const ariaLabel = computed(() => {
  const parts = segments.value.map((s) => `${s.label}: ${formatValue(s.count)}`);
  return `${props.title}. ${parts.join('. ')}`;
});

const destroyChart = () => {
  chart = destroyChartInstance(chart);
};

const renderChart = () => {
  destroyChart();
  if (!canvasRef.value || !hasData.value) return;

  const datasets = segments.value.map((seg, idx) => ({
    label: seg.label,
    data: [seg.count],
    backgroundColor: colorAt(idx),
    borderWidth: 0,
    barThickness: 28,
  }));

  chart = new Chart(canvasRef.value, {
    type: 'bar',
    data: {
      labels: [''],
      datasets,
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: chartAnimationOption(segments.value.length),
      scales: {
        x: {
          stacked: true,
          beginAtZero: true,
          grid: { color: ANALYTICS_PALETTE.grid },
          ticks: {
            color: ANALYTICS_PALETTE.muted,
            callback(value) {
              return props.valuePrefix
                ? `${props.valuePrefix}${Number(value).toLocaleString()}`
                : value;
            },
          },
        },
        y: {
          stacked: true,
          grid: { display: false },
          ticks: { display: false },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              return `${ctx.dataset.label}: ${formatValue(ctx.raw)}`;
            },
          },
        },
      },
    },
  });
};

onMounted(renderChart);
watch(() => [props.segments, props.colors], () => renderChart(), { deep: true });
onBeforeUnmount(destroyChart);
</script>
