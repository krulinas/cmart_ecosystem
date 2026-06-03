<template>
  <div>
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Staff Review</div>
        <div class="mt-1 text-3xl font-extrabold text-brand-600">{{ kpi.pendingStaff }}</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Boss Review</div>
        <div class="mt-1 text-3xl font-extrabold text-purple-600">{{ kpi.pendingBoss }}</div>
      </div>
      <div class="ml-card">
        <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Needs Revision</div>
        <div class="mt-1 text-3xl font-extrabold text-brand-600">{{ kpi.needsRevision }}</div>
      </div>
    </section>

    <section class="ml-card mb-6">
      <h2 class="text-lg font-extrabold text-ink-900 mb-1">{{ queueTitle }}</h2>
      <p class="text-sm text-ink-500 mb-4">{{ queueDescription }}</p>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-ink-500 border-b border-ink-200">
              <th class="px-3 py-2">ID</th>
              <th class="px-3 py-2">Category</th>
              <th class="px-3 py-2">Product Details</th>
              <th class="px-3 py-2">Space</th>
              <th class="px-3 py-2">Date</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink-100">
            <tr v-for="b in queueBookings" :key="'q-' + b.id">
              <td class="px-3 py-3 font-semibold">#{{ b.id }}</td>
              <td class="px-3 py-3 text-ink-700">{{ b.product_category || 'Others' }}</td>
              <td class="px-3 py-3 text-ink-700">{{ b.product_details || '—' }}</td>
              <td class="px-3 py-3">{{ b.space?.space_size || b.space_id }}</td>
              <td class="px-3 py-3">{{ b.booking_date }}</td>
              <td class="px-3 py-3"><span :class="badgeClass(b.approval_status)">{{ b.approval_status }}</span></td>
              <td class="px-3 py-3">
                <div class="flex justify-end gap-2 flex-wrap">
                  <button class="ml-btn-ghost" @click="viewPdf(b.id)">PDF</button>
                  <button v-if="effectiveRole === 'cmart_staff'" class="ml-btn-success" @click="updateStatus(b.id, 'Pending_Boss')">Pass to Boss</button>
                  <button v-if="effectiveRole === 'cmart_staff'" class="ml-btn-danger" @click="updateStatus(b.id, 'Rejected')">Reject</button>
                  <button v-if="effectiveRole === 'cmart_staff'" class="ml-btn-danger" @click="requestRevision(b.id)">Revision</button>
                  <button v-if="effectiveRole === 'cmart_admin'" class="ml-btn-success" @click="updateStatus(b.id, 'Approved')">Approve</button>
                  <button v-if="effectiveRole === 'cmart_admin'" class="ml-btn-danger" @click="updateStatus(b.id, 'Rejected')">Reject</button>
                  <button v-if="effectiveRole === 'cmart_admin'" class="ml-btn-danger" @click="requestRevision(b.id)">Revision</button>
                </div>
              </td>
            </tr>
            <tr v-if="!queueBookings.length">
              <td colspan="7" class="px-3 py-8 text-center text-ink-500">No bookings in this queue.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">All Bookings Registry</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-ink-500 border-b border-ink-200">
              <th class="px-3 py-2">ID</th>
              <th class="px-3 py-2">Vendor</th>
              <th class="px-3 py-2">Category</th>
              <th class="px-3 py-2">Product Details</th>
              <th class="px-3 py-2">Space</th>
              <th class="px-3 py-2">Date</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink-100">
            <tr v-for="b in allBookings" :key="b.id" class="hover:bg-ink-50/60">
              <td class="px-3 py-3 font-semibold">#{{ b.id }}</td>
              <td class="px-3 py-3">{{ b.user?.name || '—' }}</td>
              <td class="px-3 py-3">{{ b.product_category || 'Others' }}</td>
              <td class="px-3 py-3">{{ b.product_details || '—' }}</td>
              <td class="px-3 py-3">{{ b.space?.space_size || b.space_id }}</td>
              <td class="px-3 py-3">{{ b.booking_date }}</td>
              <td class="px-3 py-3"><span :class="badgeClass(b.approval_status)">{{ b.approval_status }}</span></td>
              <td class="px-3 py-3 text-right">
                <button class="ml-btn-ghost text-rose-600" @click="deleteBooking(b.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../services/api';
import { useBossPreviewStore } from '../../stores/bossPreview';

const emit = defineEmits(['refreshed']);

const toast = useToast();
const bossPreview = useBossPreviewStore();
const allBookings = ref([]);

const effectiveRole = computed(() => bossPreview.effectiveRole);

const queueStatus = computed(() => (effectiveRole.value === 'cmart_admin' ? 'Pending_Boss' : 'Pending_Staff'));
const queueBookings = computed(() => allBookings.value.filter((b) => b.approval_status === queueStatus.value));
const queueTitle = computed(() =>
  effectiveRole.value === 'cmart_admin' ? 'Tier 2 Boss Approval Queue' : 'Tier 1 Staff Approval Queue',
);
const queueDescription = computed(() =>
  effectiveRole.value === 'cmart_admin'
    ? 'Only bookings with Pending_Boss status are shown.'
    : 'Only bookings with Pending_Staff status are shown.',
);

const kpi = computed(() => {
  const counts = { pendingStaff: 0, pendingBoss: 0, needsRevision: 0 };
  for (const b of allBookings.value) {
    if (b.approval_status === 'Pending_Staff') counts.pendingStaff++;
    if (b.approval_status === 'Pending_Boss') counts.pendingBoss++;
    if (b.approval_status === 'Needs_Revision') counts.needsRevision++;
  }
  return counts;
});

const badgeClass = (status) => {
  if (status === 'Pending_Staff') return 'ml-badge bg-brand-100 text-brand-800';
  if (status === 'Pending_Boss') return 'ml-badge bg-purple-100 text-purple-800';
  if (status === 'Needs_Revision') return 'ml-badge bg-brand-100 text-brand-800';
  if (status === 'Approved') return 'ml-badge-approved';
  if (status === 'Rejected') return 'ml-badge-rejected';
  return 'ml-badge bg-ink-100 text-ink-700';
};

const fetchBookings = async () => {
  const { data } = await api.get('/bookings');
  allBookings.value = Array.isArray(data) ? data : (data.data ?? []);
  emit('refreshed');
};

const updateStatus = async (id, status, revisionComment = null) => {
  const payload = { approval_status: status };
  if (revisionComment) payload.revision_comment = revisionComment;
  try {
    await api.put(`/bookings/${id}`, payload);
    toast.success(`Booking #${id} updated to ${status}.`);
    await fetchBookings();
  } catch (e) {
    toast.error(e.forbiddenMessage || e.response?.data?.message || 'Unable to update booking.');
  }
};

const requestRevision = async (id) => {
  const comment = window.prompt('Enter formal revision instructions for the vendor.');
  if (!comment?.trim()) {
    toast.error('Revision instructions are required.');
    return;
  }
  await updateStatus(id, 'Needs_Revision', comment.trim());
};

const deleteBooking = async (id) => {
  if (!window.confirm(`Delete booking #${id}? This cannot be undone.`)) return;
  await api.delete(`/bookings/${id}`);
  toast.success(`Booking #${id} deleted.`);
  await fetchBookings();
};

const viewPdf = async (bookingId) => {
  const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
  const fileUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
  window.open(fileUrl, '_blank', 'noopener,noreferrer');
  setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
};

onMounted(fetchBookings);

defineExpose({ fetchBookings });
</script>
