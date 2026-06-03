<template>
  <section class="space-y-6">
    <div v-if="loading" class="text-center text-ink-500 py-12">Loading revenue analytics…</div>

    <template v-else-if="data">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Total Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-brand-600">RM {{ formatMoney(data.summary.total_revenue) }}</div>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Paid</div>
          <div class="mt-1 text-2xl font-extrabold text-emerald-600">RM {{ formatMoney(data.summary.paid_revenue) }}</div>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Unpaid</div>
          <div class="mt-1 text-2xl font-extrabold text-rose-600">RM {{ formatMoney(data.summary.unpaid_revenue) }}</div>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">F&B Share</div>
          <div class="mt-1 text-2xl font-extrabold text-ink-900">{{ data.summary.fb_share_percent }}%</div>
          <p class="text-xs text-ink-500 mt-1">{{ data.summary.fb_approved_count }} of {{ data.summary.approved_bookings }} approved</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-2">Revenue by Category</h3>
          <div class="relative h-64">
            <canvas ref="categoryCanvas"></canvas>
          </div>
        </div>
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-2">Payment Status</h3>
          <div class="relative h-64">
            <canvas ref="paymentCanvas"></canvas>
          </div>
        </div>
      </div>

      <div class="ml-card">
        <h3 class="text-lg font-extrabold text-ink-900 mb-4">Space Utilization &amp; Profitability</h3>
        <p class="text-sm text-ink-500 mb-4">
          <span v-if="data.summary.reference_event">Reference event: {{ data.summary.reference_event }}.</span>
          Approved bookings vs max slots:
          <strong>{{ data.summary.approved_bookings }}</strong>
          <span v-if="data.summary.max_slots_reference"> / {{ data.summary.max_slots_reference }}</span>
          <span v-if="data.summary.utilization_percent != null"> ({{ data.summary.utilization_percent }}% fill)</span>
        </p>

        <div class="grid grid-cols-2 gap-4 mb-4">
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
        <button @click="calculateProfit" class="ml-btn-primary" :disabled="calcLoading">Calculate Profit</button>
        <div
          v-if="profitResult"
          class="mt-4 rounded-xl border p-4 text-sm"
          :class="profitResult.is_profitable ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'"
        >
          Net profit: <strong>RM {{ profitResult.net_profit }}</strong> — {{ profitResult.message }}
        </div>

        <ul v-if="data.by_space?.length" class="mt-6 space-y-2 text-sm">
          <li v-for="row in data.by_space" :key="row.space_size" class="flex justify-between border-b border-ink-100 pb-2">
            <span>{{ row.space_size }}</span>
            <span class="font-semibold">{{ row.count }} bookings · RM {{ formatMoney(row.revenue) }}</span>
          </li>
        </ul>
      </div>
    </template>

    <div v-else-if="error" class="ml-card text-rose-600 text-sm">{{ error }}</div>
  </section>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';
import api from '../../services/api';

const toast = useToast();
const loading = ref(false);
const calcLoading = ref(false);
const error = ref('');
const data = ref(null);
const profitResult = ref(null);
const categoryCanvas = ref(null);
const paymentCanvas = ref(null);
let categoryChart = null;
let paymentChart = null;

const calcData = reactive({
  space_id: 1,
  parking_lots_used: 10,
  regular_parking_rate: 1,
  hours_occupied: 8,
});

const formatMoney = (v) => Number(v ?? 0).toFixed(2);

const destroyCharts = () => {
  if (categoryChart) {
    categoryChart.destroy();
    categoryChart = null;
  }
  if (paymentChart) {
    paymentChart.destroy();
    paymentChart = null;
  }
};

const renderCharts = () => {
  if (!data.value) return;
  destroyCharts();

  const categories = data.value.by_category || {};
  const catLabels = Object.keys(categories);
  const catValues = Object.values(categories);

  if (categoryCanvas.value && catLabels.length) {
    categoryChart = new Chart(categoryCanvas.value, {
      type: 'doughnut',
      data: {
        labels: catLabels,
        datasets: [{
          data: catValues,
          backgroundColor: ['#ea580c', '#29B6F6', '#8b5cf6', '#10b981', '#f43f5e', '#64748b'],
          borderWidth: 0,
        }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    });
  }

  const payments = data.value.by_payment_status || [];
  if (paymentCanvas.value && payments.length) {
    paymentChart = new Chart(paymentCanvas.value, {
      type: 'bar',
      data: {
        labels: payments.map((p) => p.payment_status),
        datasets: [{
          label: 'Amount (RM)',
          data: payments.map((p) => Number(p.total)),
          backgroundColor: '#29B6F6',
        }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    });
  }
};

const load = async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data: res } = await api.get('/boss/analytics/revenue');
    data.value = res;
    await nextTick();
    renderCharts();
  } catch (e) {
    error.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load revenue analytics.';
    if (e.response?.status === 403) {
      toast.error(error.value);
    }
  } finally {
    loading.value = false;
  }
};

const calculateProfit = async () => {
  calcLoading.value = true;
  try {
    const { data: res } = await api.post('/profitability', calcData);
    profitResult.value = res;
  } catch (e) {
    toast.error(e.forbiddenMessage || e.response?.data?.message || 'Profitability calculation failed.');
  } finally {
    calcLoading.value = false;
  }
};

watch(data, async () => {
  await nextTick();
  renderCharts();
});

onMounted(load);
onBeforeUnmount(destroyCharts);

defineExpose({ load });
</script>
