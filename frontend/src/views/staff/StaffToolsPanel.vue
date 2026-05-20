<template>
  <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900">Profitability Calculator</h2>
      <p class="text-sm text-ink-500 mb-4">Compare carboot revenue against lost parking income.</p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="ml-label">Space ID</label>
          <input type="number" v-model.number="calcData.space_id" class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Spaces Closed</label>
          <input type="number" v-model.number="calcData.parking_lots_used" class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Parking Rate / Hour (RM)</label>
          <input type="number" v-model.number="calcData.regular_parking_rate" class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Event Duration (Hours)</label>
          <input type="number" v-model.number="calcData.hours_occupied" class="ml-input" />
        </div>
      </div>
      <button @click="calculateProfit" class="ml-btn-primary mt-5">Calculate Profit</button>
      <div v-if="profitResult" class="mt-4 rounded-xl border p-4 text-sm" :class="profitResult.is_profitable ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'">
        Net profit: <strong>RM {{ profitResult.net_profit }}</strong> — {{ profitResult.message }}
      </div>
    </div>

    <div class="ml-card">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-extrabold text-ink-900">Advanced Analytics</h2>
        <span class="ml-badge" :class="analyticsOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
          {{ analyticsOnline ? 'live' : 'offline' }}
        </span>
      </div>
      <div class="mt-4 relative h-72">
        <canvas ref="chartCanvas"></canvas>
        <div v-if="!analyticsOnline" class="absolute inset-0 flex items-center justify-center text-sm text-ink-500">
          Start Python service on port 8001.
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';
import api from '../../services/api';

const PYTHON_API = 'http://localhost:8001/api';
const toast = useToast();
const analyticsOnline = ref(false);
const chartCanvas = ref(null);
const profitResult = ref(null);
let chartInstance = null;

const calcData = reactive({
  space_id: 1,
  parking_lots_used: 10,
  regular_parking_rate: 1,
  hours_occupied: 8,
});

const calculateProfit = async () => {
  try {
    const { data } = await api.post('/profitability', calcData);
    profitResult.value = data;
    toast.success('Profitability calculation completed.');
  } catch {
    toast.error('Profitability calculation failed.');
  }
};

const fetchPythonAnalytics = async () => {
  try {
    const { data } = await axios.get(`${PYTHON_API}/analytics/status-summary`);
    analyticsOnline.value = true;
    renderChart(data.status_breakdown || {});
  } catch {
    analyticsOnline.value = false;
    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }
  }
};

const renderChart = (statuses) => {
  if (!chartCanvas.value) return;
  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(chartCanvas.value, {
    type: 'doughnut',
    data: {
      labels: ['Staff Review', 'Boss Review', 'Needs Revision', 'Approved', 'Rejected'],
      datasets: [{
        data: [
          statuses.Pending_Staff || 0,
          statuses.Pending_Boss || 0,
          statuses.Needs_Revision || 0,
          statuses.Approved || 0,
          statuses.Rejected || 0,
        ],
        backgroundColor: ['#29B6F6', '#8b5cf6', '#0277BD', '#10b981', '#f43f5e'],
        borderWidth: 0,
      }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
  });
};

const refresh = async () => {
  await fetchPythonAnalytics();
};

onBeforeUnmount(() => {
  if (chartInstance) chartInstance.destroy();
});

defineExpose({ refresh });
</script>
