<template>
  <div class="rounded-xl border border-sky-100/80 bg-white p-3">
    <div class="mb-2 flex items-end justify-between gap-2">
      <h4 class="text-sm font-extrabold text-ink-900">{{ title }}</h4>
      <p v-if="denominator != null" class="text-xs font-semibold text-ink-500">n = {{ denominator }}</p>
    </div>
    <p v-if="!normalizedRows.length" class="text-sm text-ink-500">{{ emptyText }}</p>
    <ul v-else class="space-y-2">
      <li v-for="row in normalizedRows" :key="row.key || row.label">
        <div class="mb-1 flex justify-between gap-2 text-xs">
          <span class="font-semibold text-ink-800">{{ row.label }}</span>
          <span class="shrink-0 text-ink-600">{{ row.display || `${row.count}` }}</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-sky-50">
          <div
            class="h-full rounded-full bg-brand-600 transition-all"
            :style="{ width: `${Math.min(Number(row.percent) || 0, 100)}%` }"
          />
        </div>
      </li>
    </ul>
    <canvas v-if="normalizedRows.length && showChart" ref="canvasRef" class="mt-3 max-h-40" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { withHumanizedLabels } from '../../utils/analyticsLabels';

const props = defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
  denominator: { type: Number, default: null },
  emptyText: { type: String, default: 'No data for this distribution.' },
  /** Prefer bar list only; set true for an optional chart detail view. */
  showChart: { type: Boolean, default: false },
});

const canvasRef = ref(null);
let chart;

const normalizedRows = computed(() => withHumanizedLabels(props.rows));

const renderChart = () => {
  if (!props.showChart || !canvasRef.value || !normalizedRows.value.length) return;
  chart?.destroy();
  chart = new Chart(canvasRef.value, {
    type: 'bar',
    data: {
      labels: normalizedRows.value.map((r) => r.label),
      datasets: [{
        label: 'Count',
        data: normalizedRows.value.map((r) => r.count || 0),
        backgroundColor: '#0277BD',
        borderRadius: 6,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const row = normalizedRows.value[ctx.dataIndex];
              return row?.display || `${ctx.parsed.x}`;
            },
          },
        },
      },
      scales: {
        x: { beginAtZero: true, ticks: { precision: 0 } },
      },
    },
  });
};

watch(normalizedRows, () => renderChart(), { deep: true });
watch(() => props.showChart, () => renderChart());
onMounted(renderChart);
onBeforeUnmount(() => chart?.destroy());
</script>
