<template>
  <section class="space-y-6">
    <div v-if="loading" class="text-center text-ink-500 py-12">Loading revenue analytics…</div>

    <template v-else-if="data">
      <div
        data-testid="carboot-analytics-guide"
        class="rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 to-cyan-50 px-5 py-4"
      >
        <h2 class="text-sm font-bold text-sky-950">How to read this dashboard</h2>
        <p class="mt-2 text-sm leading-relaxed text-sky-900/90">
          This dashboard separates expected revenue from collected revenue. Expected revenue comes from approved booking invoices.
          Collected revenue only counts bookings marked as Paid. Outstanding revenue shows approved invoices that still need payment follow-up.
          Pending verification payments are not counted as collected until verified. Category Mix is based on approved booking count, not revenue.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Total Expected Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-brand-600">RM {{ formatMoney(summary.total_revenue) }}</div>
          <p data-testid="expected-revenue-helper" class="text-xs text-ink-500 mt-2">
            <span class="font-medium text-ink-600">What this means:</span>
            Approved booking invoices, paid and unpaid.
          </p>
          <p class="text-xs text-ink-400 mt-1">Expected = Paid + Unpaid approved invoices</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Collected Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-emerald-600">RM {{ formatMoney(summary.paid_revenue) }}</div>
          <p data-testid="collected-revenue-helper" class="text-xs text-ink-500 mt-2">
            <span class="font-medium text-ink-600">What this means:</span>
            Only bookings marked as Paid.
          </p>
          <p class="text-xs text-ink-400 mt-1">Collected = Paid invoices</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Outstanding Revenue</div>
          <div class="mt-1 text-2xl font-extrabold text-rose-600">RM {{ formatMoney(summary.unpaid_revenue) }}</div>
          <p data-testid="outstanding-revenue-helper" class="text-xs text-ink-500 mt-2">
            <span class="font-medium text-ink-600">What this means:</span>
            Approved invoices still unpaid.
          </p>
          <p class="text-xs text-ink-400 mt-1">Outstanding = Expected − Collected</p>
        </div>
        <div class="ml-card">
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Category Mix</div>
          <div class="mt-1 text-2xl font-extrabold text-ink-900">{{ summary.fb_share_percent }}%</div>
          <p data-testid="category-mix-helper" class="text-xs text-ink-500 mt-2">
            <span class="font-medium text-ink-600">What this means:</span>
            Based on approved booking count, not revenue.
          </p>
          <p class="text-xs text-ink-400 mt-1">
            Category Mix = category approved bookings ÷ total approved bookings
          </p>
          <p class="text-xs text-ink-500 mt-1">
            Food &amp; Beverages share of {{ summary.approved_bookings }} approved bookings
            ({{ summary.fb_approved_count }} F&amp;B)
          </p>
        </div>
      </div>

      <div
        data-testid="revenue-insight-summary"
        class="ml-card border-sky-100 bg-gradient-to-br from-white to-sky-50/40"
      >
        <h3 class="text-lg font-extrabold text-ink-900">Carboot Operations Insight</h3>
        <ul class="mt-3 space-y-2 text-sm text-ink-700">
          <li v-for="(line, index) in insightLines" :key="index" class="flex gap-2">
            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500" aria-hidden="true" />
            <span>{{ line }}</span>
          </li>
        </ul>
        <p
          v-if="outstandingRevenue > 0"
          class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900"
        >
          Follow up unpaid approved bookings.
        </p>
        <p
          v-if="hasPendingVerification"
          class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900"
        >
          Pending verification payments are waiting for staff/manager confirmation
          ({{ pendingVerification.count }} invoice{{ pendingVerification.count === 1 ? '' : 's' }},
          RM {{ formatMoney(pendingVerification.amount) }}).
        </p>
        <p
          v-if="fillRateActionNote"
          class="mt-3 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-900"
        >
          {{ fillRateActionNote }}
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Bookings by Category</h3>
          <p class="text-xs text-ink-500 mb-3">This chart shows approved booking count by vendor category.</p>
          <div v-if="hasCategoryChartData" class="relative h-64">
            <canvas ref="categoryCanvas"></canvas>
          </div>
          <div
            v-else
            data-testid="bookings-category-empty-state"
            class="flex h-64 items-center justify-center rounded-xl border border-dashed border-ink-200 bg-ink-50/50 px-4 text-center text-sm text-ink-500"
          >
            No approved category data yet.
          </div>
        </div>
        <div class="ml-card">
          <h3 class="text-lg font-extrabold text-ink-900 mb-1">Payment Status</h3>
          <p class="text-xs text-ink-500 mb-3">This chart groups invoice amounts by payment status.</p>
          <div v-if="hasPaymentChartData" class="relative h-64">
            <canvas ref="paymentCanvas"></canvas>
          </div>
          <div
            v-else
            data-testid="payment-status-empty-state"
            class="flex h-64 items-center justify-center rounded-xl border border-dashed border-ink-200 bg-ink-50/50 px-4 text-center text-sm text-ink-500"
          >
            No payment status data available yet.
          </div>
        </div>
      </div>

      <div class="ml-card space-y-6">
        <div>
          <h3 class="text-lg font-extrabold text-ink-900 mb-2">Event Space Snapshot</h3>
          <p class="text-sm text-ink-500">
            <span v-if="summary.reference_event">Reference event: {{ summary.reference_event }}.</span>
            Approved bookings vs max slots:
            <strong>{{ summary.approved_bookings }}</strong>
            <span v-if="summary.max_slots_reference"> / {{ summary.max_slots_reference }}</span>
            <span v-if="summary.utilization_percent != null"> ({{ summary.utilization_percent }}% fill)</span>
          </p>
          <p
            v-if="fillRateInterpretation"
            data-testid="event-space-interpretation"
            class="mt-2 text-sm text-ink-700"
          >
            {{ fillRateInterpretation }}
          </p>
          <p
            v-else-if="summary.max_slots_reference"
            data-testid="event-space-interpretation"
            class="mt-2 text-sm text-ink-500"
          >
            Not enough capacity data yet to calculate fill rate.
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

const summary = computed(() => data.value?.summary ?? {});

const expectedRevenue = computed(() => Number(summary.value.total_revenue ?? 0));
const collectedRevenue = computed(() => Number(summary.value.paid_revenue ?? 0));
const outstandingRevenue = computed(() => Number(summary.value.unpaid_revenue ?? 0));
const approvedBookings = computed(() => Number(summary.value.approved_bookings ?? 0));

const collectionPercent = computed(() => {
  if (expectedRevenue.value <= 0) return 0;
  return Math.round((collectedRevenue.value / expectedRevenue.value) * 100);
});

const pendingVerification = computed(() => {
  const payments = data.value?.by_payment_status || [];
  const row = payments.find((p) => p.payment_status === 'Pending Verification');
  return {
    count: Number(row?.count ?? 0),
    amount: Number(row?.total ?? 0),
  };
});

const hasPendingVerification = computed(() =>
  pendingVerification.value.count > 0 || pendingVerification.value.amount > 0,
);

const insightLines = computed(() => {
  const lines = [];

  if (approvedBookings.value === 0) {
    lines.push('No approved bookings yet, so revenue insights are not available.');
    return lines;
  }

  if (expectedRevenue.value <= 0) {
    lines.push('Approved bookings exist, but invoice totals are not available yet.');
    return lines;
  }

  if (collectedRevenue.value === expectedRevenue.value) {
    lines.push('All approved booking revenue has been collected.');
  } else {
    lines.push(
      `Revenue collection is ${collectionPercent.value}% complete. RM${formatMoney(outstandingRevenue.value)} remains outstanding from approved but unpaid bookings.`,
    );
  }

  if (outstandingRevenue.value > 0) {
    lines.push('Action needed: follow up approved bookings that are still unpaid.');
  }

  if (hasPendingVerification.value) {
    lines.push('Pending verification payments are not included in collected revenue until they are verified.');
  }

  return lines;
});

const fillRateInterpretation = computed(() => {
  const pct = summary.value.utilization_percent;
  if (pct == null) return null;
  if (pct < 30) return 'Low fill rate. There is still plenty of booth capacity available.';
  if (pct < 80) return 'Moderate fill rate. Continue monitoring vendor demand.';
  return 'High fill rate. Event is close to capacity.';
});

const fillRateActionNote = computed(() => {
  const pct = summary.value.utilization_percent;
  if (pct == null || approvedBookings.value === 0) return '';
  if (pct < 30) return 'Event still has available capacity. Consider promotion or vendor outreach.';
  if (pct >= 80) return 'Event is close to capacity. Monitor booth availability.';
  return '';
});

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
