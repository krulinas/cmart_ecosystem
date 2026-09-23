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
    <ul v-if="hasData" class="mt-2 space-y-1 text-[11px] text-ink-600" :aria-label="title">
      <li v-for="row in ranked" :key="row.key" class="flex justify-between gap-2">
        <span class="truncate">{{ row.label }}</span>
        <span class="shrink-0 tabular-nums">
          {{ row.count }}
          <template v-if="row.percent != null"> · {{ row.percent }}%</template>
        </span>
      </li>
    </ul>
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
  /** When true, sort by count descending (ranked). */
  sortByCount: { type: Boolean, default: true },
  color: { type: String, default: ANALYTICS_PALETTE.primary },
  emptyText: { type: String, default: '' },
  testId: { type: String, default: 'analytics-ranked-bar-chart' },
});

const canvasRef = ref(null);
let chart = null;

const ranked = computed(() => {
  const rows = presentNumericRows(props.rows, 'count').filter((r) => r.count > 0);
  if (!props.sortByCount) return rows;
  return [...rows].sort((a, b) => b.count - a.count);
});

const hasData = computed(() => ranked.value.length > 0);
const chartHeight = computed(() => Math.max(160, ranked.value.length * 36));
const ariaLabel = computed(() => {
  const parts = ranked.value.map((r) => `${r.label}: ${r.count}`);
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
      labels: ranked.value.map((r) => r.label),
      datasets: [{
        label: props.title,
        data: ranked.value.map((r) => r.count),
        backgroundColor: withAlpha(props.color, 0.7),
        borderColor: props.color,
        borderWidth: 1,
        borderRadius: 4,
        barThickness: 16,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: chartAnimationOption(ranked.value.length),
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: ANALYTICS_PALETTE.grid },
          ticks: { color: ANALYTICS_PALETTE.muted, precision: 0 },
        },
        y: {
          grid: { display: false },
          ticks: { color: ANALYTICS_PALETTE.ink },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const row = ranked.value[ctx.dataIndex];
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
watch(() => [props.rows, props.color, props.sortByCount], () => renderChart(), { deep: true });
onBeforeUnmount(destroyChart);
</script>
