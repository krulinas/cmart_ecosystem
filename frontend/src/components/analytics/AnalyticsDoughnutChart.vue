<template>
  <div class="rounded-xl border border-sky-100 bg-white p-3" :data-testid="testId">
    <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
      </div>
    </div>

    <p v-if="!hasData" class="py-6 text-center text-sm text-ink-500">{{ emptyText || 'No data available.' }}</p>
    <div v-else class="flex flex-col items-center gap-3 sm:flex-row sm:items-center">
      <div class="relative mx-auto w-full max-w-[220px]" :style="{ height: `${chartHeight}px` }">
        <canvas ref="canvasRef" role="img" :aria-label="ariaLabel" tabindex="0" />
        <div
          v-if="centerLabel || centerValue != null"
          class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
          aria-hidden="true"
        >
          <p v-if="centerValue != null" class="text-xl font-extrabold text-ink-900">{{ centerValue }}</p>
          <p v-if="centerLabel" class="text-[10px] font-semibold uppercase tracking-wide text-ink-500">{{ centerLabel }}</p>
        </div>
      </div>
      <ul class="w-full space-y-1.5 text-xs text-ink-700 sm:flex-1" :aria-label="title">
        <li
          v-for="(item, idx) in segments"
          :key="item.key || idx"
          class="flex items-center justify-between gap-2 rounded-lg border border-ink-100 px-2 py-1.5"
        >
          <span class="inline-flex min-w-0 items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ background: colorAt(idx) }" />
            <span class="truncate font-semibold text-ink-800">{{ item.label }}</span>
          </span>
          <span class="shrink-0 tabular-nums text-ink-600">
            {{ item.count }}
            <template v-if="item.percent != null"> · {{ item.percent }}%</template>
          </span>
        </li>
      </ul>
    </div>
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
  /** Rows with count (or value). Missing/null counts are excluded — never coerced to zero. */
  rows: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [...COMPOSITION_COLORS] },
  centerValue: { type: [String, Number], default: null },
  centerLabel: { type: String, default: '' },
  emptyText: { type: String, default: '' },
  testId: { type: String, default: 'analytics-doughnut-chart' },
  chartHeight: { type: Number, default: 180 },
});

const canvasRef = ref(null);
let chart = null;

const segments = computed(() => presentNumericRows(props.rows, 'count'));
const hasData = computed(() => segments.value.some((s) => s.count > 0) || segments.value.length > 0);
const ariaLabel = computed(() => {
  const parts = segments.value.map((s) => `${s.label}: ${s.count}`);
  return `${props.title}. ${parts.join('. ')}`;
});

const colorAt = (idx) => props.colors[idx % props.colors.length] || ANALYTICS_PALETTE.primary;

const destroyChart = () => {
  chart = destroyChartInstance(chart);
};

const renderChart = () => {
  destroyChart();
  if (!canvasRef.value || !hasData.value) return;

  const labels = segments.value.map((s) => s.label);
  const data = segments.value.map((s) => s.count);
  const backgroundColor = segments.value.map((_, i) => colorAt(i));

  chart = new Chart(canvasRef.value, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor,
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverOffset: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
      animation: chartAnimationOption(data.length),
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const row = segments.value[ctx.dataIndex];
              if (!row) return ctx.label;
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
watch(() => [props.rows, props.colors, props.centerValue], () => {
  renderChart();
}, { deep: true });
onBeforeUnmount(destroyChart);
</script>
