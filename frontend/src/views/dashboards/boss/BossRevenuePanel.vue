<template>
  <section class="space-y-6">
    <div v-if="loading" class="text-center text-ink-500 py-12">Loading revenue analytics…</div>

    <template v-else-if="data">
      <p class="text-xs text-ink-500">
        Pending verification payments are not included in collected revenue until verified.
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Total Expected Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-brand-600">RM {{ formatMoney(data.summary.total_revenue) }}</div>
          <p class="text-xs text-ink-500 mt-1">From approved booking invoices</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Collected Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-emerald-600">RM {{ formatMoney(data.summary.paid_revenue) }}</div>
          <p class="text-xs text-ink-500 mt-1">Paid invoices only</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Outstanding Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-rose-600">RM {{ formatMoney(data.summary.unpaid_revenue) }}</div>
          <p class="text-xs text-ink-500 mt-1">Currently unpaid invoices (excludes pending verification)</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Category Mix</div>
          <div class="mt-1 text-2xl font-extrabold text-ink-900">{{ data.summary.fb_share_percent }}%</div>
          <p class="text-xs text-ink-500 mt-1">
            Food &amp; Beverages share of {{ data.summary.approved_bookings }} approved bookings
            ({{ data.summary.fb_approved_count }} F&amp;B)
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Bookings by Category</h3>
          <p class="text-xs text-ink-500 mb-3">Based on approved booking count, not revenue amount.</p>
          <div v-if="hasCategoryChartData" class="relative h-64">
            <canvas ref="categoryCanvas"></canvas>
          </div>
          <div
            v-else
            class="flex h-64 items-center justify-center rounded-xl border border-dashed border-ink-200 bg-ink-50/50 px-4 text-center text-sm text-ink-500"
          >
            No approved booking category data yet.
            <span class="block mt-1 text-xs">Charts will appear once approved bookings exist.</span>
          </div>
        </div>
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Payment Status</h3>
          <p class="text-xs text-ink-500 mb-3">Invoice amounts grouped by payment status.</p>
          <div v-if="hasPaymentChartData" class="relative h-64">
            <canvas ref="paymentCanvas"></canvas>
          </div>
          <div
            v-else
            class="flex h-64 items-center justify-center rounded-xl border border-dashed border-ink-200 bg-ink-50/50 px-4 text-center text-sm text-ink-500"
          >
            No payment status data yet.
            <span class="block mt-1 text-xs">Charts will appear once approved bookings and invoices exist.</span>
          </div>
        </div>
      </div>

      <div class="ml-card space-y-6">
        <div>
          <h3 class="text-lg font-extrabold text-ink-900 mb-2">Event Space Snapshot</h3>
          <p class="text-sm text-ink-500">
            <span v-if="data.summary.reference_event">Reference event: {{ data.summary.reference_event }}.</span>
            Approved bookings vs max slots:
            <strong>{{ data.summary.approved_bookings }}</strong>
            <span v-if="data.summary.max_slots_reference"> / {{ data.summary.max_slots_reference }}</span>
            <span v-if="data.summary.utilization_percent != null"> ({{ data.summary.utilization_percent }}% fill)</span>
          </p>
          <p class="mt-2 text-xs text-amber-800/90 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
            Current fill indicator is a basic reference only and will be refined with vendor capacity tracking.
          </p>

          <ul v-if="data.by_space?.length" class="mt-4 space-y-2 text-sm">
            <li v-for="row in data.by_space" :key="row.space_size" class="flex justify-between border-b border-ink-100 pb-2">
              <span>{{ row.space_size }}</span>
              <span class="font-semibold">{{ row.count }} bookings · RM {{ formatMoney(row.revenue) }}</span>
            </li>
          </ul>
        </div>

        <div class="border-t border-ink-100 pt-6">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Profit Simulator</h3>
          <p class="text-xs text-ink-500 mb-4">
            This simulator estimates parking opportunity cost based on manual assumptions.
            It is not a final branch profit report.
          </p>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="ml-label">Space ID <span class="text-ink-400 font-normal">(manual)</span></label>
              <input type="number" v-model.number="calcData.space_id" class="ml-input" />
            </div>
            <div>
              <label class="ml-label">Parking lots used <span class="text-ink-400 font-normal">(manual)</span></label>
              <input type="number" v-model.number="calcData.parking_lots_used" class="ml-input" />
            </div>
            <div>
              <label class="ml-label">Parking rate / hour (RM) <span class="text-ink-400 font-normal">(manual)</span></label>
              <input type="number" v-model.number="calcData.regular_parking_rate" class="ml-input" />
            </div>
            <div>
              <label class="ml-label">Event duration (hours) <span class="text-ink-400 font-normal">(manual)</span></label>
              <input type="number" v-model.number="calcData.hours_occupied" class="ml-input" />
            </div>
          </div>
          <button @click="calculateProfit" class="ml-btn-primary" :disabled="calcLoading">Run simulator</button>
          <div
            v-if="profitResult"
            class="mt-4 rounded-xl border p-4 text-sm"
            :class="profitResult.is_profitable ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'"
          >
            Simulated net: <strong>RM {{ profitResult.net_profit }}</strong> — {{ profitResult.message }}
          </div>
        </div>
      </div>
    </template>

    <div v-else-if="error" class="ml-card text-rose-600 text-sm">{{ error }}</div>
  </section>
</template>

<script setup>
import { ref, reactive, computed, onBeforeUnmount, watch, nextTick } from 'vue';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';

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

const hasCategoryChartData = computed(() => {
  const categories = data.value?.by_category || {};
  return Object.keys(categories).length > 0;
});

const hasPaymentChartData = computed(() => (data.value?.by_payment_status || []).length > 0);

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
          label: 'Approved bookings',
          data: catValues,
          backgroundColor: ['#ea580c', '#29B6F6', '#8b5cf6', '#10b981', '#f43f5e', '#64748b'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.label}: ${ctx.parsed} booking(s)`,
            },
          },
        },
      },
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
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: {
            callbacks: {
              afterLabel: (ctx) => {
                const row = payments[ctx.dataIndex];
                return row?.count != null ? `${row.count} invoice(s)` : '';
              },
            },
          },
        },
      },
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
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Profitability calculation failed.');
    }
  } finally {
    calcLoading.value = false;
  }
};

watch(data, async () => {
  await nextTick();
  renderCharts();
});

onBeforeUnmount(destroyCharts);

defineExpose({ load });
</script>
