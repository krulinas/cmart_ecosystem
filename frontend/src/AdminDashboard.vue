<template>
  <WorkspaceShell
    title="CMart Workspace"
    :subtitle="queueSubtitle"
    :workspace-label="auth.role === 'cmart_admin' ? 'CMart · Tier 2' : 'CMart · Tier 1'"
    :user-name="auth.user?.name || 'CMart Staff'"
    :user-role-label="auth.role === 'cmart_admin' ? 'CMart Admin' : 'CMart Staff'"
  >
    <template #actions>
      <button class="ml-btn-ghost" @click="refreshAll" :disabled="loading">
        <span>↻</span>
        <span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span>
      </button>
      <button class="ml-btn-ghost" @click="logout">
        Logout
      </button>
    </template>

    <!-- KPI strip -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Staff Review</div>
        <div class="mt-1 text-3xl font-extrabold text-brand-600">{{ kpi.pendingStaff }}</div>
        <div class="mt-1 text-xs text-ink-500">pending Tier 1 review</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Boss Review</div>
        <div class="mt-1 text-3xl font-extrabold text-purple-600">{{ kpi.pendingBoss }}</div>
        <div class="mt-1 text-xs text-ink-500">pending Tier 2 review</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Needs Revision</div>
        <div class="mt-1 text-3xl font-extrabold text-brand-600">{{ kpi.needsRevision }}</div>
        <div class="mt-1 text-xs text-ink-500">returned for correction</div>
      </div>
    </section>

    <!-- Approval queue -->
    <section id="queue" class="ml-card mb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-extrabold text-ink-900">{{ queueTitle }}</h2>
          <p class="text-sm text-ink-500">{{ queueDescription }}</p>
        </div>
        <span class="ml-badge bg-ink-100 text-ink-700">{{ queueBookings.length }} pending</span>
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
            <tr v-for="b in queueBookings" :key="b.id" class="hover:bg-ink-50/60 transition">
              <td class="px-3 py-3 font-semibold text-ink-900">#{{ b.id }}</td>
              <td class="px-3 py-3 text-ink-700">{{ b.space?.space_size || `Space ${b.space_id}` }}</td>
              <td class="px-3 py-3 text-ink-700">{{ b.booking_date }}</td>
              <td class="px-3 py-3">
                <span :class="badgeClass(b.approval_status)">{{ b.approval_status }}</span>
              </td>
              <td class="px-3 py-3">
                <div class="flex justify-end gap-2">
                  <button
                    @click="viewPdf(b.id)"
                    class="ml-btn-ghost"
                  >View PDF</button>
                  <button
                    v-if="auth.role === 'cmart_staff'"
                    @click="updateStatus(b.id, 'Pending_Boss')"
                    class="ml-btn-success"
                  >Pass to Boss</button>
                  <button
                    v-if="auth.role === 'cmart_staff'"
                    @click="requestRevision(b.id)"
                    class="ml-btn-danger"
                  >Request Revision</button>
                  <button
                    v-if="auth.role === 'cmart_admin'"
                    @click="updateStatus(b.id, 'Approved')"
                    class="ml-btn-success"
                  >Final Approve</button>
                  <button
                    v-if="auth.role === 'cmart_admin'"
                    @click="requestRevision(b.id)"
                    class="ml-btn-danger"
                  >Return to Staff/Vendor</button>
                </div>
              </td>
            </tr>
            <tr v-if="!queueBookings.length">
              <td colspan="5" class="px-3 py-10 text-center text-ink-500">
                <div class="text-2xl mb-2">🎉</div>
                <div class="font-semibold text-ink-700">No Pending Records</div>
                <div class="text-xs">There are no bookings requiring review.</div>
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
            <label class="ml-label">Space ID (Space Size)</label>
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
import { useRouter } from 'vue-router';
import WorkspaceShell from './layouts/WorkspaceShell.vue';
import api from './services/api';
import { useAuthStore } from './stores/auth';

const PYTHON_API = 'http://localhost:8001/api';

const toast = useToast();
const router = useRouter();
const auth = useAuthStore();
const loading = ref(false);
const analyticsOnline = ref(false);
const chartCanvas = ref(null);
let chartInstance = null;

const allBookings = ref([]);
const queueStatus = computed(() => auth.role === 'cmart_admin' ? 'Pending_Boss' : 'Pending_Staff');
const queueBookings = computed(() =>
  allBookings.value.filter(b => b.approval_status === queueStatus.value)
);
const queueTitle = computed(() =>
  auth.role === 'cmart_admin' ? 'Tier 2 Boss Approval Queue' : 'Tier 1 Staff Approval Queue'
);
const queueSubtitle = computed(() =>
  auth.role === 'cmart_admin'
    ? 'Perform final approval for bookings escalated by CMart staff.'
    : 'Review vendor submissions before escalation to the boss approval tier.'
);
const queueDescription = computed(() =>
  auth.role === 'cmart_admin'
    ? 'Only bookings with Pending_Boss status are shown.'
    : 'Only bookings with Pending_Staff status are shown.'
);

const kpi = computed(() => {
  const counts = { pendingStaff: 0, pendingBoss: 0, needsRevision: 0 };
  for (const b of allBookings.value) {
    if (b.approval_status === 'Pending_Staff')  counts.pendingStaff++;
    if (b.approval_status === 'Pending_Boss') counts.pendingBoss++;
    if (b.approval_status === 'Needs_Revision') counts.needsRevision++;
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
  if (status === 'Pending_Staff') return 'ml-badge bg-brand-100 text-brand-800';
  if (status === 'Pending_Boss') return 'ml-badge bg-purple-100 text-purple-800';
  if (status === 'Needs_Revision') return 'ml-badge bg-brand-100 text-brand-800';
  if (status === 'Approved') return 'ml-badge-approved';
  if (status === 'Rejected') return 'ml-badge-rejected';
  return 'ml-badge bg-ink-100 text-ink-700';
};

const fetchBookings = async () => {
  try {
    const { data } = await api.get('/bookings');
    allBookings.value = Array.isArray(data) ? data : (data.data ?? []);
  } catch (e) {
    console.error('500 Internal Server Error: Unable to retrieve booking data from the API.', e);
    toast.error('500 Internal Server Error: Unable to retrieve booking data from the API.');
  }
};

const updateStatus = async (id, status, revisionComment = null) => {
  try {
    const payload = { approval_status: status };
    if (revisionComment) payload.revision_comment = revisionComment;

    await api.put(`/bookings/${id}`, payload);
    const target = allBookings.value.find(b => b.id === id);
    if (target) target.approval_status = status;
    toast.success(`200 OK: Booking #${id} status updated to ${status}.`);
    fetchPythonAnalytics();

    const message = `Carboot@CMart notification: Your booking (ID: ${id}) has been ${status}.`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
  } catch (e) {
    console.error(`500 Internal Server Error: Unable to update booking #${id}.`, e);
    toast.error(`500 Internal Server Error: Unable to update booking #${id}.`);
  }
};

const requestRevision = async (id) => {
  const comment = window.prompt('Enter formal revision instructions for the vendor.');
  if (!comment || !comment.trim()) {
    toast.error('400 Bad Request: Revision instructions are required.');
    return;
  }

  await updateStatus(id, 'Needs_Revision', comment.trim());
};

const viewPdf = async (bookingId) => {
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
  } catch (e) {
    console.error(`500 Internal Server Error: Unable to retrieve PDF for booking #${bookingId}.`, e);
    toast.error(`500 Internal Server Error: Unable to retrieve PDF for booking #${bookingId}.`);
  }
};

const calculateProfit = async () => {
  try {
    const { data } = await api.post('/profitability', calcData);
    profitResult.value = data;
    toast.success('200 OK: Profitability calculation completed.');
  } catch (e) {
    console.error('500 Internal Server Error: Profitability calculation failed.', e);
    toast.error('500 Internal Server Error: Profitability calculation failed.');
  }
};

const fetchPythonAnalytics = async () => {
  try {
    const { data } = await axios.get(`${PYTHON_API}/analytics/status-summary`);
    const statuses = data.status_breakdown || {};
    analyticsOnline.value = true;
    renderChart(statuses);
  } catch (e) {
    console.warn('503 Service Unavailable: Analytics API is not available.', e?.message);
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

const logout = async () => {
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

onMounted(refreshAll);
onBeforeUnmount(() => { if (chartInstance) chartInstance.destroy(); });
</script>
