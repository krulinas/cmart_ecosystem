<template>
  <WorkspaceShell
    title="CMart Workspace"
    subtitle="Approve vendor bookings, calculate profitability, and monitor live carboot analytics."
    workspace-label="CMart · Staff"
  >
    <template #actions>
      <button class="ml-btn-ghost" @click="refreshAll" :disabled="loading">
        <span>↻</span>
        <span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span>
      </button>
    </template>

    <!-- KPI strip -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Pending</div>
        <div class="mt-1 text-3xl font-extrabold text-amber-600">{{ kpi.pending }}</div>
        <div class="mt-1 text-xs text-ink-500">awaiting your approval</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Approved</div>
        <div class="mt-1 text-3xl font-extrabold text-emerald-600">{{ kpi.approved }}</div>
        <div class="mt-1 text-xs text-ink-500">vendors confirmed</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Rejected</div>
        <div class="mt-1 text-3xl font-extrabold text-rose-600">{{ kpi.rejected }}</div>
        <div class="mt-1 text-xs text-ink-500">declined applications</div>
      </div>
    </section>

    <!-- Approval queue -->
    <section id="queue" class="ml-card mb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-extrabold text-ink-900">Admin Approval Queue</h2>
          <p class="text-sm text-ink-500">Vendor bookings waiting for your decision.</p>
        </div>
        <span class="ml-badge bg-ink-100 text-ink-700">{{ pendingBookings.length }} pending</span>
      </div>

      <div class="overflow-x-auto -mx-2">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-ink-500 border-b border-ink-200">
              <th class="px-3 py-2 font-semibold">Booking</th>
              <th class="px-3 py-2 font-semibold">Space</th>
              <th class="px-3 py-2 font-semibold">Date</th>
              <th class="px-3 py-2 font-semibold">Status</th>
              <th class="px-3 py-2 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink-100">
            <tr v-for="b in pendingBookings" :key="b.id" class="hover:bg-ink-50/60 transition">
              <td class="px-3 py-3 font-semibold text-ink-900">#{{ b.id }}</td>
              <td class="px-3 py-3 text-ink-700">Space {{ b.space_id }}</td>
              <td class="px-3 py-3 text-ink-700">{{ b.booking_date }}</td>
              <td class="px-3 py-3">
                <span :class="badgeClass(b.approval_status)">{{ b.approval_status }}</span>
              </td>
              <td class="px-3 py-3">
                <div class="flex justify-end gap-2">
                  <button
                    v-if="b.approval_status === 'Pending'"
                    @click="updateStatus(b.id, 'Approved')"
                    class="ml-btn-success"
                  >Approve</button>
                  <button
                    v-if="b.approval_status === 'Pending'"
                    @click="updateStatus(b.id, 'Rejected')"
                    class="ml-btn-danger"
                  >Reject</button>
                </div>
              </td>
            </tr>
            <tr v-if="!pendingBookings.length">
              <td colspan="5" class="px-3 py-10 text-center text-ink-500">
                <div class="text-2xl mb-2">🎉</div>
                <div class="font-semibold text-ink-700">All caught up</div>
                <div class="text-xs">No pending bookings right now.</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Profitability + Analytics grid -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Profitability -->
      <div id="profitability" class="ml-card">
        <h2 class="text-lg font-extrabold text-ink-900">Profitability Calculator</h2>
        <p class="text-sm text-ink-500 mb-4">FR4 — compare carboot revenue against lost parking income.</p>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="ml-label">Space ID (Tapak Size)</label>
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

        <button @click="calculateProfit" class="ml-btn-primary mt-5 w-full sm:w-auto">
          Calculate Profit
        </button>

        <div
          v-if="profitResult"
          class="mt-5 rounded-xl border p-4"
          :class="profitResult.is_profitable
            ? 'bg-emerald-50 border-emerald-200'
            : 'bg-rose-50 border-rose-200'"
        >
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-ink-500">Parking Revenue Lost</div>
              <div class="font-bold text-ink-900">RM {{ profitResult.lost_parking_revenue }}</div>
            </div>
            <div>
              <div class="text-ink-500">Carboot Revenue</div>
              <div class="font-bold text-ink-900">RM {{ profitResult.event_revenue }}</div>
            </div>
          </div>
          <div class="mt-3 pt-3 border-t" :class="profitResult.is_profitable ? 'border-emerald-200' : 'border-rose-200'">
            <div class="text-xs uppercase tracking-wider font-semibold"
                 :class="profitResult.is_profitable ? 'text-emerald-700' : 'text-rose-700'">
              Net Profit
            </div>
            <div class="text-2xl font-extrabold"
                 :class="profitResult.is_profitable ? 'text-emerald-700' : 'text-rose-700'">
              RM {{ profitResult.net_profit }}
            </div>
            <div class="text-xs text-ink-600 mt-1">{{ profitResult.message }}</div>
          </div>
        </div>
      </div>

      <!-- Analytics -->
      <div id="analytics" class="ml-card">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-extrabold text-ink-900">Advanced Analytics</h2>
            <p class="text-sm text-ink-500">FR5 — live data from the Python microservice (:8001).</p>
          </div>
          <span
            class="ml-badge"
            :class="analyticsOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
          >
            {{ analyticsOnline ? '● live' : '● offline' }}
          </span>
        </div>

        <div class="mt-4 relative h-72">
          <canvas ref="chartCanvas"></canvas>
          <div
            v-if="!analyticsOnline"
            class="absolute inset-0 flex items-center justify-center text-sm text-ink-500"
          >
            Microservice unavailable. Start <code class="px-1 rounded bg-ink-100">uvicorn main:app --port 8001</code> to view.
          </div>
        </div>
      </div>
    </section>
  </WorkspaceShell>
</template>

<script setup>
import { ref, reactive, onMounted, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';
import WorkspaceShell from './layouts/WorkspaceShell.vue';

const LARAVEL_API = 'http://127.0.0.1:8000/api';
const PYTHON_API = 'http://localhost:8001/api';

const toast = useToast();
const loading = ref(false);
const analyticsOnline = ref(false);
const chartCanvas = ref(null);
let chartInstance = null;

const allBookings = ref([]);
const pendingBookings = computed(() =>
  allBookings.value.filter(b => b.approval_status === 'Pending')
);

const kpi = computed(() => {
  const counts = { pending: 0, approved: 0, rejected: 0 };
  for (const b of allBookings.value) {
    if (b.approval_status === 'Pending')  counts.pending++;
    if (b.approval_status === 'Approved') counts.approved++;
    if (b.approval_status === 'Rejected') counts.rejected++;
  }
  return counts;
});

const calcData = reactive({
  space_id: 4,
  parking_lots_used: 10,
  regular_parking_rate: 1,
  hours_occupied: 8,
});
const profitResult = ref(null);

const badgeClass = (status) => {
  if (status === 'Pending')  return 'ml-badge-pending';
  if (status === 'Approved') return 'ml-badge-approved';
  if (status === 'Rejected') return 'ml-badge-rejected';
  return 'ml-badge bg-ink-100 text-ink-700';
};

const fetchBookings = async () => {
  try {
    const { data } = await axios.get(`${LARAVEL_API}/bookings`);
    allBookings.value = Array.isArray(data) ? data : (data.data ?? []);
  } catch (e) {
    console.error('Bookings fetch error:', e);
    toast.error('Failed to fetch bookings. Is the Laravel server running on :8000?');
  }
};

const updateStatus = async (id, status) => {
  try {
    await axios.put(`${LARAVEL_API}/bookings/${id}`, { approval_status: status });
    const target = allBookings.value.find(b => b.id === id);
    if (target) target.approval_status = status;
    toast.success(`Booking #${id} ${status.toLowerCase()}.`);
    fetchPythonAnalytics();

    const message = `Hello from CMART! Your Carboot tapak booking (ID: ${id}) has been officially ${status}. Thank you!`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
  } catch (e) {
    console.error('Update error:', e);
    toast.error(`Unable to update booking #${id}.`);
  }
};

const calculateProfit = async () => {
  try {
    const { data } = await axios.post(`${LARAVEL_API}/profitability`, calcData);
    profitResult.value = data;
    toast.success('Profitability calculated.');
  } catch (e) {
    console.error('Profitability error:', e);
    toast.error('Calculation failed. Please verify your input.');
  }
};

const fetchPythonAnalytics = async () => {
  try {
    const { data } = await axios.get(`${PYTHON_API}/analytics/status-summary`);
    const statuses = data.status_breakdown || {};
    analyticsOnline.value = true;
    renderChart(statuses);
  } catch (e) {
    console.warn('Python microservice offline:', e?.message);
    analyticsOnline.value = false;
    if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
  }
};

const renderChart = (statuses) => {
  if (!chartCanvas.value) return;
  if (chartInstance) chartInstance.destroy();

  chartInstance = new Chart(chartCanvas.value, {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Pending', 'Rejected'],
      datasets: [{
        data: [
          statuses.Approved || 0,
          statuses.Pending  || 0,
          statuses.Rejected || 0,
        ],
        backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: { position: 'bottom' },
      },
    },
  });
};

const refreshAll = async () => {
  loading.value = true;
  await Promise.allSettled([fetchBookings(), fetchPythonAnalytics()]);
  loading.value = false;
};

onMounted(refreshAll);
onBeforeUnmount(() => { if (chartInstance) chartInstance.destroy(); });
</script>
